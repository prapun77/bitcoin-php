<?php

namespace BitWasp\Bitcoin\Chain;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

class ProofOfWork
{
    const DIFF_PRECISION = 12;
    const POW_2_256 = '115792089237316195423570985008687907853269984665640564039457584007913129639936';

    /**
     * @var Math
     */
    private $math;

    /**
     * @var ParamsInterface
     */
    private $params;

    /**
     * @param Math $math
     * @param ParamsInterface $params
     */
    public function __construct(Math $math, ParamsInterface $params)
    {
        $this->math = $math;
        $this->params = $params;
    }

    /**
     * @param BufferInterface $bits
     * @return \GMP
     */
    public function getTarget(BufferInterface $bits)
    {
        $negative = false;
        $overflow = false;
        return $this->math->decodeCompact($bits->getInt(), $negative, $overflow);
    }

    /**
     * @return \GMP
     */
    public function getMaxTarget()
    {
        return $this->getTarget(Buffer::int($this->params->powBitsLimit(), 4, $this->math));
    }

    /**
     * @param BufferInterface $bits
     * @return BufferInterface
     */
    public function getTargetHash(BufferInterface $bits)
    {
        return Buffer::int(
            gmp_strval($this->getTarget($bits), 10),
            32,
            $this->math
        );
    }

    /**
     * @param BufferInterface $bits
     * @return string
     */
    public function getDifficulty(BufferInterface $bits)
    {
        $target = $this->getTarget($bits);
        $lowest = $this->getMaxTarget();
        $lowest = $this->math->mul($lowest, $this->math->pow(gmp_init(10, 10), self::DIFF_PRECISION));
        
        $difficulty = str_pad($this->math->toString($this->math->div($lowest, $target)), self::DIFF_PRECISION + 1, '0', STR_PAD_LEFT);
        
        $intPart = substr($difficulty, 0, 0 - self::DIFF_PRECISION);
        $decPart = substr($difficulty, 0 - self::DIFF_PRECISION, self::DIFF_PRECISION);
        
        return $intPart . '.' . $decPart;
    }

    /**
     * @param BufferInterface $hash
     * @param int $nBits
     * @return bool
     */
    public function check(BufferInterface $hash, $nBits)
    {
        $negative = false;
        $overflow = false;
        
        $target = $this->math->decodeCompact($nBits, $negative, $overflow);
        if ($negative || $overflow || $this->math->cmp($target, gmp_init(0)) === 0 ||  $this->math->cmp($target, $this->getMaxTarget()) > 0) {
            throw new \RuntimeException('nBits below minimum work');
        }

        if ($this->math->cmp(gmp_init($hash->getInt(), 10), $target) > 0) {
            throw new \RuntimeException("Hash doesn't match nBits");
        }

        return true;
    }

    /**
     * @param BlockHeaderInterface $header
     * @return bool
     * @throws \Exception
     */
    public function checkHeader(BlockHeaderInterface $header)
    {
        return $this->check($header->getHash(), $header->getBits());
    }

    /**
     * @param BufferInterface $bits
     * @return int|string
     */
    public function getWork(BufferInterface $bits)
    {
        $target = gmp_strval($this->getTarget($bits), 10);
        return bcdiv(self::POW_2_256, $target);
    }
}
