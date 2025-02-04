<?php

namespace ZuluCrypto\StellarSdk\Model;

use ZuluCrypto\StellarSdk\XdrModel\Operation\BumpSequenceOp;

/**
 * See: https://www.stellar.org/developers/horizon/reference/resources/operation.html
 */
class Operation extends RestApiModel
{
    public const TYPE_CREATE_ACCOUNT       = 'create_account';
    public const TYPE_PAYMENT              = 'payment';
    public const TYPE_PATH_PAYMENT         = 'path_payment';
    public const TYPE_MANAGE_OFFER         = 'manage_offer';
    public const TYPE_CREATE_PASSIVE_OFFER = 'create_passive_offer';
    public const TYPE_SET_OPTIONS          = 'set_options';
    public const TYPE_CHANGE_TRUST         = 'change_trust';
    public const TYPE_ALLOW_TRUST          = 'allow_trust';
    public const TYPE_ACCOUNT_MERGE        = 'account_merge';
    public const TYPE_INFLATION            = 'inflation';
    public const TYPE_MANAGE_DATA          = 'manage_data';
    public const TYPE_BUMP_SEQUENCE        = 'bump_sequence';

    /**
     * Operation ID on the Stellar network
     *
     * @var string
     */
    protected $id;

    /**
     * String representation of type
     *
     * @var string
     */
    protected $type;

    /**
     * Stellar integer type code, see Stellar docs and constants in each class
     *
     * @var int
     */
    protected $typeI;

    /**
     * @var string
     */
    protected $transactionHash;

    /**
     * @param array $rawData
     * @return Operation
     */
    public static function fromRawResponseData($rawData)
    {
        // Start with a generic Operation
        $object = new Operation($rawData['id'], $rawData['type']);

        // Create subclasses for recognized types
        switch ($rawData['type']) {
            case Operation::TYPE_CREATE_ACCOUNT:
                $object = new CreateAccountOperation($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_PAYMENT:
                $object = new Payment($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_PATH_PAYMENT:
                $object = new PathPayment($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_MANAGE_OFFER:
                $object = new ManageOfferOperation($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_CREATE_PASSIVE_OFFER:
                $object = new CreatePassiveOfferOperation($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_SET_OPTIONS:
                $object = new SetOptionsOperation($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_CHANGE_TRUST:
                $object = new ChangeTrustOperation($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_ALLOW_TRUST:
                $object = new AllowTrustOperation($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_ACCOUNT_MERGE:
                $object = new AccountMergeOperation($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_INFLATION:
                $object = new InflationOperation($rawData['id'], $rawData['type']);
                break;
            case Operation::TYPE_BUMP_SEQUENCE:
                $object = new BumpSequenceOp($rawData['id'], $rawData['type']);
                break;
        }

        $object->loadFromRawResponseData($rawData);

        return $object;
    }

    /**
     * Operation constructor.
     *
     * @param $id
     * @param $type
     */
    public function __construct($id, $type)
    {
        $this->id = $id;
        $this->type = $type;
    }

    /**
     * @param $rawData
     */
    public function loadFromRawResponseData($rawData)
    {
        parent::loadFromRawResponseData($rawData);

        $this->id = $rawData['id'];
        $this->type = $rawData['type'];
        $this->typeI = $rawData['type_i'];

        if (isset($rawData['paging_token'])) {
            $this->pagingToken = $rawData['paging_token'];
        }
        if (isset($rawData['transaction_hash'])) {
            $this->transactionHash = $rawData['transaction_hash'];
        }
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getTypeI()
    {
        return $this->typeI;
    }

    /**
     * @param int $typeI
     */
    public function setTypeI($typeI)
    {
        $this->typeI = $typeI;
    }

    /**
     * @return string
     */
    public function getTransactionHash()
    {
        return $this->transactionHash;
    }
}
