<?php

namespace ZuluCrypto\StellarSdk;


use Prophecy\Exception\InvalidArgumentException;
use ZuluCrypto\StellarSdk\Horizon\ApiClient;
use ZuluCrypto\StellarSdk\Horizon\Exception\HorizonException;
use ZuluCrypto\StellarSdk\Model\Account;
use ZuluCrypto\StellarSdk\Model\Payment;
use ZuluCrypto\StellarSdk\Signing\SigningInterface;
use ZuluCrypto\StellarSdk\Transaction\TransactionBuilder;
use ZuluCrypto\StellarSdk\XdrModel\AccountId;
use ZuluCrypto\StellarSdk\XdrModel\Asset;

class Server
{
    /**
     * @var ApiClient
     */
    private $apiClient;

    /**
     * @var
     */
    private $isTestnet;


    /**
     * @var SigningInterface
     */
    protected $signingProvider;

    /**
     * @return Server
     */
    public static function testNet()
    {
        $server = new Server(ApiClient::newTestnetClient());
        $server->isTestnet = true;

        return $server;
    }

    /**
     * @return Server
     */
    public static function publicNet()
    {
        $server = new Server(ApiClient::newPublicClient());

        return $server;
    }

    /**
     * Connects to a custom network
     *
     * @param $horizonBaseUrl
     * @param $networkPassphrase
     * @return Server
     */
    public static function customNet($horizonBaseUrl, $networkPassphrase)
    {
        return new Server(ApiClient::newCustomClient($horizonBaseUrl, $networkPassphrase));
    }

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
        $this->isTestnet = false;
    }

    /**
     * Returns the Account that matches $accountId or null if the account does
     * not exist
     *
     * @param $accountId Keypair|string the public account ID
     * @return Account|null
     * @throws Horizon\Exception\HorizonException
     */
    public function getAccount($accountId)
    {
        // Cannot be empty
        if (!$accountId) throw new InvalidArgumentException('Empty accountId');

        if ($accountId instanceof Keypair) {
            $accountId = $accountId->getPublicKey();
        }

        try {
            $response = $this->apiClient->get(sprintf('/accounts/%s', $accountId));
        }
        catch (HorizonException $e) {
            // Account not found, return null
            if ($e->getHttpStatusCode() === 404) {
                return null;
            }

            // A problem we can't handle, rethrow
            throw $e;
        }

        $account = Account::fromHorizonResponse($response);
        $account->setApiClient($this->apiClient);

        return $account;
    }

    /**
     * Returns all accounts who are trustees to a specific asset.
     *
     * @param \ZuluCrypto\StellarSdk\XdrModel\Asset $asset Every account in the result will have a trustline
     * for the given asset. must be either alphanum4, or alphanum12
     * @param string $order
     * @param int $limit
     * @return Account[]
     * @throws \ZuluCrypto\StellarSdk\Horizon\Exception\HorizonException
     */
    public function getAccountsForAsset(Asset $asset, string $order = 'asc', int $limit = 10): array
    {
        function encodeAsset(Asset $asset): string
        {
            switch ($asset->getType()) {
                case Asset::TYPE_NATIVE:
                    return 'native';
                case Asset::TYPE_ALPHANUM_4:
                case Asset::TYPE_ALPHANUM_12:
                    return $asset->getAssetCode() . ':' . $asset->getIssuer()->getAccountIdString();
                default:
                    throw new \InvalidArgumentException('Invalid asset type ' . $asset->getType());
            }
        }

        if ($asset->isNative()) {
            throw new \InvalidArgumentException('Asset must be either alphanum4, or alphanum12');
        }

        if (!in_array($order, ['asc', 'desc'])) {
            throw new \InvalidArgumentException('Order must be either asc or desc');
        }

        // todo remove limit max value when implement paging, maybe -1 or null for all records
        if ($limit < 1 || $limit > 200) {
            throw new \InvalidArgumentException('Limit must be in range 1-200');
        }

        $params = [
            'asset' => encodeAsset($asset),
            'order' => $order,
            'limit' => $limit,
        ];
        $url = '/accounts' . '?' . http_build_query($params);
        $records = $this->apiClient->get($url)->getRecords();
        return array_map(fn ($r) => Account::fromHorizonResponse($r), $records);
    }

    /**
     * @param \ZuluCrypto\StellarSdk\XdrModel\AccountId $signerId Account ID of the signer. Every account in the result
     * will have the given account ID as a signer.
     * @param string $order
     * @param int $limit
     * @return Account[]
     * @throws \ZuluCrypto\StellarSdk\Horizon\Exception\HorizonException
     */
    public function getAccountsForSigner(AccountId $signerId, string $order = 'asc', int $limit = 10): array
    {
        if (!in_array($order, ['asc', 'desc'])) {
            throw new \InvalidArgumentException('Order must be either asc or desc');
        }

        // todo remove limit max value when implement paging, maybe -1 or null for all records
        if ($limit < 1 || $limit > 200) {
            throw new \InvalidArgumentException('Limit must be in range 1-200');
        }

        $params = [
            'signer' => $signerId->getAccountIdString(),
            'order' => $order,
            'limit' => $limit,
        ];
        $url = '/accounts' . '?' . http_build_query($params);
        $records = $this->apiClient->get($url)->getRecords();
        return array_map(fn ($r) => Account::fromHorizonResponse($r), $records);
    }

    /**
     * Returns true if the account exists on this server and has been funded
     *
     * @param $accountId
     * @return bool
     * @throws HorizonException
     * @throws \ErrorException
     */
    public function accountExists($accountId)
    {
        // Handle basic errors such as malformed account IDs
        try {
            $account = $this->getAccount($accountId);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        // Account ID may be valid but hasn't been funded yet
        if (!$account) return false;

        return $account->getNativeBalanceStroops() != '0';
    }

    /**
     * @param $accountId string|Keypair
     * @return TransactionBuilder
     */
    public function buildTransaction($accountId)
    {
        if ($accountId instanceof Keypair) {
            $accountId = $accountId->getPublicKey();
        }

        return (new TransactionBuilder($accountId))
            ->setApiClient($this->apiClient)
            ->setSigningProvider($this->signingProvider)
        ;
    }

    /**
     * @param $transactionHash
     * @return array|Payment[]
     */
    public function getPaymentsByTransactionHash($transactionHash)
    {
        $url = sprintf('/transactions/%s/payments', $transactionHash);

        $response = $this->apiClient->get($url);

        $payments = [];
        foreach ($response->getRecords() as $rawRecord) {
            $payments[] = Payment::fromRawResponseData($rawRecord);
        }

        return $payments;
    }

    /**
     * @param $accountId
     * @return bool
     * @throws Horizon\Exception\HorizonException
     */
    public function fundAccount($accountId)
    {
        if ($accountId instanceof Keypair) {
            $accountId = $accountId->getPublicKey();
        }

        try {
            $this->apiClient->get(sprintf('/friendbot?addr=%s', $accountId));
            return true;
        }
        catch (HorizonException $e) {
            // Account has already been funded
            if ($e->getHttpStatusCode() == 400) {
                return false;
            }

            // Unexpected exception
            throw $e;
        }

    }

    /**
     * Submits a base64-encoded transaction to the Stellar network.
     *
     * No additional validation is performed on this transaction
     *
     * @param $base64TransactionEnvelope
     * @return Horizon\Api\HorizonResponse
     */
    public function submitB64Transaction($base64TransactionEnvelope)
    {
        return $this->apiClient->submitB64Transaction($base64TransactionEnvelope);
    }

    /**
     * @return string
     */
    public function getHorizonBaseUrl()
    {
        return $this->apiClient->getBaseUrl();
    }

    /**
     * @return SigningInterface
     */
    public function getSigningProvider()
    {
        return $this->signingProvider;
    }

    /**
     * @param SigningInterface $signingProvider
     */
    public function setSigningProvider($signingProvider)
    {
        $this->signingProvider = $signingProvider;
    }

    /**
     * @return ApiClient
     */
    public function getApiClient()
    {
        return $this->apiClient;
    }

    /**
     * @param ApiClient $apiClient
     */
    public function setApiClient($apiClient)
    {
        $this->apiClient = $apiClient;
    }
}
