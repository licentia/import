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

namespace Licentia\Import\Block\Adminhtml\Import\Edit\Tab;

use Magento\Framework\App\ObjectManager;
use Magento\ImportExport\Model\Import;

/**
 * Class Mapping
 *
 * @package Licentia\Import\Block\Adminhtml\Import\Edit\Tab
 */
class Mapping extends \Magento\Backend\Block\Widget\Form\Generic
{

    /**
     * @var \Licentia\Import\Helper\Data
     */
    protected $importHelper;

    /**
     * @var \Magento\Store\Model\System\Store
     */
    protected $systemStore;

    /**
     * @var \Licentia\Import\Model\ImportFactory
     */
    protected $importFactory;

    /**
     * Main constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry             $registry
     * @param \Magento\Framework\Data\FormFactory     $formFactory
     * @param \Licentia\Panda\Helper\Data             $pandaHelper
     * @param \Magento\Store\Model\System\Store       $systemStore
     * @param \Licentia\Import\Model\ImportFactory    $importFactory
     * @param array                                   $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Licentia\Import\Helper\Data $importHelper,
        \Magento\Store\Model\System\Store $systemStore,
        \Licentia\Import\Model\ImportFactory $importFactory,
        array $data = []
    ) {

        $this->importHelper = $importHelper;
        $this->importFactory = $importFactory;
        $this->systemStore = $systemStore;
        parent::__construct($context, $registry, $formFactory, $data);

        $this->setTemplate('mappings.phtml');

    }

    /**
     * @return mixed|null
     */
    public function getImport()
    {

        /** @var \Licentia\Import\Model\Import $current */
        return $this->_coreRegistry->registry('panda_import');
    }
}
