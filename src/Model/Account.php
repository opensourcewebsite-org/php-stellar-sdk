<?php

namespace ZuluCrypto\StellarSdk\Model;

use ErrorException;
use Exception;
use InvalidArgumentException;
use phpseclib3\Math\BigInteger;
use ZuluCrypto\StellarSdk\Horizon\Api\HorizonResponse;
use ZuluCrypto\StellarSdk\Keypair;
use ZuluCrypto\StellarSdk\Transaction\TransactionBuilder;
use ZuluCrypto\StellarSdk\Util\MathSafety;
use ZuluCrypto\StellarSdk\XdrModel\Asset;
use ZuluCrypto\StellarSdk\XdrModel\Operation\PaymentOp;

/**
 * See: https://www.stellar.org/developers/horizon/reference/resources/account.html
 *
 * Account viewer:
 *  https://www.stellar.org/laboratory/#explorer
 */
class Account extends RestApiModel
{
    protected $id;

    private string $accountId;

    /**
     * NOTE: for the BigInteger representation of this, see $this->getSequenceAsBigInteger()
     * @var string
     */
    private string $sequence;

    private int $subentryCount;

    private ?string $homeDomain;

    private string $lastModifiedLedger;

    /**
     * @var array|AssetAmount[]
     */
    private array $balances;

    private array $thresholds;

    private ?array $flags;

    private ?array $signers;

    private array $data;

    /**
     * @param HorizonResponse $response
     * @return Account
     */
    public static function fromHorizonResponse(HorizonResponse $response): Account
    {
        $rawData = $response->getRawData();

        return self::fromRawResponseData($rawData);
    }

    /**
     * @param array $rawData
     * @return \ZuluCrypto\StellarSdk\Model\Account
     */
    public static function fromRawResponseData(array $rawData): Account
    {
        // 404 means the account does not currently exist (it may have been merged)
        if (isset($rawData['status']) && $rawData['status'] == 404) {
            throw new InvalidArgumentException('Account does not exist');
        }

        // Generic catch for other errors
        if (isset($rawData['status']) && $rawData['status'] !== 200) {
            throw new InvalidArgumentException('Cannot create account due to error response');
        }

        $object = new Account($rawData['id']);

        $object->accountId = $rawData['account_id'];
        $object->sequence = $rawData['sequence'];
        $object->subentryCount = $rawData['subentry_count'];
        $object->homeDomain = $rawData['home_domain'] ?? null;
        $object->lastModifiedLedger = $rawData['last_modified_ledger'];
        $object->thresholds = $rawData['thresholds'];
        $object->flags = $rawData['flags'] ?? null;
        $object->signers = $rawData['signers'] ?? null;
        $object->data = [];
        if (isset($rawData['data'])) {
            foreach ($rawData['data'] as $key => $value) {
                $object->data[$key] = base64_decode($value);
            }
        }

        if (isset($rawData['balances'])) {
            foreach ($rawData['balances'] as $rawBalance) {
                $balance = new AssetAmount($rawBalance['balance'], $rawBalance['asset_type']);

                if (!$balance->isNativeAsset()) {
                    if (!$balance->isLiquidityPoolSharesAsset()) {
                        $balance->setAssetCode($rawBalance['asset_code']);
                        $balance->setAssetIssuerAccountId($rawBalance['asset_issuer']);
                    }

                    $balance->setLimit($rawBalance['limit']);
                }

                $object->balances[] = $balance;
            }
        }

        return $object;
    }

    /**
     * Returns true if the specified account ID (G....) passes basic validity checks
     *
     * Note that this doesn't necessarily mean the account is funded or exists
     * on the network. To check that, use the Server::getAccount() method.
     *
     * @param string $accountId
     * @return bool
     */
    public static function isValidAccount(string $accountId): bool
    {
        // Validate that keypair passes checksum
        try {
            Keypair::newFromPublicKey($accountId);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function __construct($id)
    {
        $this->id = $id;

        $this->balances = [];
    }

    /**
     * @param                 $toAccountId
     * @param                 $amount
     * @param string|string[] $signingKeys
     * @return HorizonResponse
     * @throws \ErrorException
     */
    public function sendNativeAsset($toAccountId, $amount, $signingKeys): HorizonResponse
    {
        $payment = Payment::newNativeAssetPayment($toAccountId, $amount, $this->accountId);

        return $this->sendPayment($payment, $signingKeys);
    }

    /**
     * @param Payment $payment
     * @param         $signingKeys
     * @return HorizonResponse
     * @throws \ErrorException
     */
    public function sendPayment(Payment $payment, $signingKeys): HorizonResponse
    {
        if ($payment->isNativeAsset()) {
            $paymentOp = PaymentOp::newNativePayment($payment->getDestinationAccountId(), $payment->getAmount()->getBalanceAsStroops());
        } else {
            throw new ErrorException('Not implemented');
        }

        $transaction = (new TransactionBuilder($this->accountId))
            ->setApiClient($this->apiClient)
            ->addOperation(
                $paymentOp
            )
        ;

        return $transaction->submit($signingKeys);
    }

    /**
     * @param null $sinceCursor
     * @param int $limit
     * @param string $order
     * @return Transaction[]
     * @throws \ZuluCrypto\StellarSdk\Horizon\Exception\HorizonException
     */
    public function getTransactions($sinceCursor = null, int $limit = 50, string $order = 'asc'): array
    {
        $transactions = [];

        $url = sprintf('/accounts/%s/transactions', $this->accountId);
        $params = [];

        if ($sinceCursor) {
            $params['cursor'] = $sinceCursor;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($order) {
            $params['order'] = $order;
        }

        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $response = $this->apiClient->get($url);
        $rawTransactions = $response->getRecords();

        foreach ($rawTransactions as $rawTransaction) {
            $transaction = Transaction::fromRawResponseData($rawTransaction);
            $transaction->setApiClient($this->getApiClient());

            $transactions[] = $transaction;
        }

        return $transactions;
    }

    /**
     * @param null $sinceCursor
     * @param int $limit
     * @return array
     * @throws \ZuluCrypto\StellarSdk\Horizon\Exception\HorizonException
     */
    public function getEffects($sinceCursor = null, int $limit = 50): array
    {
        $effects = [];
        $url = sprintf('/accounts/%s/effects', $this->accountId);
        $params = [];

        if ($sinceCursor) {
            $params['cursor'] = $sinceCursor;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }

        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $response = $this->apiClient->get($url);
        $raw = $response->getRecords();

        foreach ($raw as $rawEffect) {
            $effect = Effect::fromRawResponseData($rawEffect);
            $effect->setApiClient($this->getApiClient());

            $effects[] = $effect;
        }

        return $effects;
    }

    /**
     * @param null $sinceCursor
     * @param int $limit
     * @param string $order
     * @return array|AssetTransferInterface[]|RestApiModel[]
     * @throws \ZuluCrypto\StellarSdk\Horizon\Exception\HorizonException
     */
    public function getPayments($sinceCursor = null, int $limit = 50, string $order = 'asc'): array
    {
        $results = [];

        $url = sprintf('/accounts/%s/payments', $this->accountId);
        $params = [];

        if ($sinceCursor) {
            $params['cursor'] = $sinceCursor;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($order) {
            $params['order'] = $order;
        }

        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $response = $this->apiClient->get($url);
        $rawRecords = $response->getRecords($limit);

        foreach ($rawRecords as $rawRecord) {
            switch ($rawRecord['type']) {
                case 'create_account':
                    $result = CreateAccountOperation::fromRawResponseData($rawRecord);
                    break;
                case 'payment':
                    $result = Payment::fromRawResponseData($rawRecord);
                    break;
                case 'account_merge':
                    $result = AccountMergeOperation::fromRawResponseData($rawRecord);
                    break;
                case 'path_payment':
                    $result = PathPayment::fromRawResponseData($rawRecord);
                    break;
            }

            $result->setApiClient($this->getApiClient());

            $results[] = $result;
        }

        return $results;
    }

    /**
     * See ApiClient::streamPayments
     *
     * @param string $sinceCursor
     * @param callable|null $callback
     */
    public function streamPayments(string $sinceCursor = 'now', callable $callback = null)
    {
        $this->apiClient->streamPayments($sinceCursor, $callback);
    }

    /**
     * Returns a string representing the native balance
     *
     * @return int|number
     * @throws \ErrorException
     */
    public function getNativeBalance()
    {
        MathSafety::require64Bit();

        foreach ($this->getBalances() as $balance) {
            if ($balance->isNativeAsset()) {
                return $balance->getBalance();
            }
        }

        return 0;
    }

    /**
     * Returns the balance in stroops
     *
     * @return string
     * @throws \ErrorException
     */
    public function getNativeBalanceStroops(): string
    {
        MathSafety::require64Bit();

        foreach ($this->getBalances() as $balance) {
            if ($balance->isNativeAsset()) {
                return $balance->getUnscaledBalance();
            }
        }

        return "0";
    }

    /**
     * Returns the numeric balance of the given asset
     *
     * @param Asset $asset
     * @return null|string
     */
    public function getCustomAssetBalanceValue(Asset $asset): ?string
    {
        foreach ($this->getBalances() as $balance) {
            if ($balance->getAssetCode() !== $asset->getAssetCode()) {
                continue;
            }
            if ($balance->getAssetIssuerAccountId() != $asset->getIssuer()->getAccountIdString()) {
                continue;
            }

            return $balance->getBalance();
        }

        return null;
    }

    /**
     * Returns an AssetAmount representing the balance of this asset
     *
     * @param Asset $asset
     * @return null|AssetAmount
     */
    public function getCustomAssetBalance(Asset $asset): ?AssetAmount
    {
        foreach ($this->getBalances() as $balance) {
            if ($balance->getAssetCode() !== $asset->getAssetCode()) {
                continue;
            }
            if ($balance->getAssetIssuerAccountId() != $asset->getIssuer()->getAccountIdString()) {
                continue;
            }

            return $balance;
        }

        return null;
    }

    /**
     * Returns the balance of a custom asset in stroops
     *
     * @param Asset $asset
     * @return null|string
     * @throws \ErrorException
     */
    public function getCustomAssetBalanceStroops(Asset $asset): ?string
    {
        MathSafety::require64Bit();

        foreach ($this->getBalances() as $balance) {
            if ($balance->getAssetCode() !== $asset->getAssetCode()) {
                continue;
            }
            if ($balance->getAssetIssuerAccountId() != $asset->getIssuer()->getAccountIdString()) {
                continue;
            }

            return $balance->getUnscaledBalance();
        }

        return null;
    }

    /**
     * Returns an array holding account thresholds.
     *
     * @return array
     */
    public function getThresholds(): array
    {
        return $this->thresholds;
    }

    /**
     * @return array
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * @return array
     */
    public function getSigners(): array
    {
        return $this->signers;
    }

    /**
     * This returns the sequence exactly as it comes back from the Horizon API
     *
     * See getSequenceAsBigInteger if you need to use this value in a transaction
     * or other 64-bit safe location.
     *
     * @return string
     */
    public function getSequence(): string
    {
        return $this->sequence;
    }

    /**
     * @return string|null
     */
    public function getHomeDomain(): ?string
    {
        return $this->homeDomain;
    }

    /**
     * @return string
     */
    public function getLastModifiedLedger(): string
    {
        return $this->lastModifiedLedger;
    }

    /**
     * @return BigInteger
     */
    public function getSequenceAsBigInteger(): BigInteger
    {
        return new BigInteger($this->sequence);
    }

    /**
     * @return array|AssetAmount[]
     */
    public function getBalances(): array
    {
        return $this->balances;
    }

    /**
     * Returns an array of key => value pairs
     *
     * Note that the values have been base64-decoded and may be binary data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Returns account id. This account’s public key encoded in a base32 string representation.
     * @return string
     */
    public function getAccountId(): string
    {
        return $this->accountId;
    }

    /**
     * @return int
     */
    public function getSubentryCount(): int
    {
        return $this->subentryCount;
    }
}
