<?php

namespace ZuluCrypto\StellarSdk\XdrModel;

use phpseclib3\Math\BigInteger;
use ZuluCrypto\StellarSdk\Model\StellarAmount;
use ZuluCrypto\StellarSdk\Xdr\XdrBuffer;

class TransactionResult
{
    public const SUCCESS               = 'success';    // all operations suceeded
    public const FAILED                = 'failed';   // one or more operations failed
    public const TOO_EARLY             = 'too_early';   // ledger close time before min timebounds
    public const TOO_LATE              = 'too_late';   // ledger close time after max timebounds
    public const MISSING_OPERATION     = 'missing_operation';   // no operations specified
    public const BAD_SEQ               = 'bad_seq';   // sequence number not correct for source account
    public const BAD_AUTH              = 'bad_auth';   // too few valid signatures or wrong network
    public const INSUFFICIENT_BALANCE  = 'insufficient_balance';   // account would be below the reserve after this tx
    public const NO_ACCOUNT            = 'no_account';   // source account not found
    public const INSUFFICIENT_FEE      = 'insufficient_fee';   // fee was too small
    public const BAD_AUTH_EXTRA        = 'bad_auth_extra';  // included extra signatures
    public const INTERNAL_ERROR        = 'internal_error';  // unknown error

    /**
     * @var StellarAmount
     */
    protected $feeCharged;

    /**
     * See the class constants
     * @var string
     */
    protected $resultCode;

    /**
     * Array of operation results
     *
     * @var OperationResult[]
     */
    protected $operationResults;

    public function __construct()
    {
        $this->operationResults = [];
    }

    /**
     * @param XdrBuffer $xdr
     * @return TransactionResult
     * @throws \ErrorException
     */
    public static function fromXdr(XdrBuffer $xdr)
    {
        $model = new TransactionResult();

        // This is the fee in stroops
        $model->feeCharged = new StellarAmount(new BigInteger($xdr->readInteger64()));

        $rawCode = $xdr->readInteger();
        $resultCodeMap = [
            '0' => 'success',
            '-1' => static::FAILED,
            '-2' => static::TOO_EARLY,
            '-3' => static::TOO_LATE,
            '-4' => static::MISSING_OPERATION,
            '-5' => static::BAD_SEQ,
            '-6' => static::BAD_AUTH,
            '-7' => static::INSUFFICIENT_BALANCE,
            '-8' => static::NO_ACCOUNT,
            '-9' => static::INSUFFICIENT_FEE,
            '-10' => static::BAD_AUTH_EXTRA,
            '-11' => static::INTERNAL_ERROR,
        ];
        if (!isset($resultCodeMap[$rawCode])) {
            throw new \ErrorException(sprintf('Unknown result code %s', $rawCode));
        }
        $model->resultCode = $resultCodeMap[$rawCode];

        $numOperations = $xdr->readInteger();
        for ($i=0; $i < $numOperations; $i++) {
            $op = OperationResult::fromXdr($xdr);
            $model->operationResults[] = $op;
        }

        return $model;
    }

    /**
     * Returns true if all operations in this transaction succeeded
     * @return bool
     */
    public function succeeded()
    {
        return $this->resultCode === static::SUCCESS;
    }

    /**
     * Returns true if any operation in the transaction failed
     * @return bool
     */
    public function failed()
    {
        return !$this->succeeded();
    }

    /**
     * @return StellarAmount
     */
    public function getFeeCharged()
    {
        return $this->feeCharged;
    }

    /**
     * @return string
     */
    public function getResultCode()
    {
        return $this->resultCode;
    }

    /**
     * @return OperationResult[]
     */
    public function getOperationResults()
    {
        return $this->operationResults;
    }
}
