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

namespace Licentia\Import\Cron;

/**
 * Class ImportData
 *
 * @package Licentia\Import\Cron
 */
class ImportData
{

    /**
     * @var \Licentia\Panda\Helper\Data
     */
    protected $pandaHelper;

    /**
     * @var \Licentia\Import\Model\ImportFactory
     */
    protected $importFactory;

    /**
     * UpdateSalesExtraCosts constructor.
     *
     * @param \Licentia\Import\Model\ImportFactory $importFactory
     * @param \Licentia\Import\Helper\Data         $pandaHelper
     */
    public function __construct(
        \Licentia\Import\Model\ImportFactory $importFactory,
        \Licentia\Import\Helper\Data $pandaHelper
    ) {

        $this->importFactory = $importFactory;
        $this->pandaHelper = $pandaHelper;
    }

    /**
     *
     */
    public function execute()
    {

        try {
            $this->importFactory->create()->executeCron();
        } catch (\Exception $e) {
            $this->pandaHelper->logWarning($e);
        }
    }
}
