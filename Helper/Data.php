<?php

/*
 * Copyright (C) Licentia, Unipessoal LDA
 *
 * NOTICE OF LICENSE
 *
 *  This source file is subject to the EULA
 *  that is bundled with this package in the file LICENSE.txt.
 *  It is also available through the world-wide-web at this URL:
 *  https://www.greenflyingpanda.com/panda-license.txt
 *
 *  @title      Licentia Panda - Magento® Sales Automation Extension
 *  @package    Licentia
 *  @author     Bento Vilas Boas <bento@licentia.pt>
 *  @copyright  Copyright (c) Licentia - https://licentia.pt
 *  @license    https://www.greenflyingpanda.com/panda-license.txt
 *
 */

namespace Licentia\Import\Helper;

/**
 * Class Data
 *
 * @package Licentia\Import\Helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encryptor;

    public function __construct(
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\App\Helper\Context $context
    ) {

        parent::__construct($context);

        $this->encryptor = $encryptor;
        $this->dateTime = $dateTime;
    }

    /**
     * @param $m
     *
     * @return mixed
     * @copyright  http://stackoverflow.com/questions/928563/evaluating-a-string-of-simple-mathematical-expressions#929681
     *
     */
    private function callback1($m)
    {

        return $this->evaluateExpression($m[1]);
    }

    /**
     * @param $n
     * @param $m
     *
     * @return float
     * @copyright http://stackoverflow.com/questions/928563/evaluating-a-string-of-simple-mathematical-expressions#929681
     *
     */
    private function callback2($n, $m)
    {

        $o = $m[0];
        $m[0] = ' ';

        return $o == '+' ? $n + $m : ($o == '-' ? $n - $m : ($o == '*' ? $n * $m : $n / $m));
    }

    /**
     * @param $s
     *
     * @return mixed
     * @copyright http://stackoverflow.com/questions/928563/evaluating-a-string-of-simple-mathematical-expressions#929681
     */
    public function evaluateExpression($s)
    {

        while ($s !=
               ($t = preg_replace_callback('/\(([^()]*)\)/', [$this, 'callback1'], $s))) {
            $s = $t;
        }
        preg_match_all('![-+/*].*?[\d.]+!', "+$s", $m);

        return array_reduce($m[0], [$this, 'callback2']);
    }

    /**
     * @param \Exception $exception
     * @param string     $level
     */
    public function logException(\Exception $exception, $level = 'critical')
    {

        $this->_logger->$level($exception);
    }

    /**
     * @param \Exception $e
     */
    public function logWarning(\Exception $e)
    {

        $this->_logger->warning($e->getMessage());
    }

    /**
     * @return \Magento\Framework\Encryption\EncryptorInterface
     */
    public function getEncryptor()
    {

        return $this->encryptor;
    }

    /**
     * @param null $format
     * @param null $input
     *
     * @return string
     */
    public function gmtDate($format = null, $input = null)
    {

        return $this->dateTime->gmtDate($format, $input);
    }

    /**
     * @param null $input
     *
     * @return string
     */
    public function gmtDateTime($input = null)
    {

        return $this->dateTime->gmtDate('Y-m-d H:i:s', $input);
    }
}
