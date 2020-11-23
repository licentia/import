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

/**
 * Class Log
 *
 * @package Licentia\Import\Block\Adminhtml\Import\Edit\Tab
 */
class Log extends \Magento\Backend\Block\Widget\Grid\Extended
{

    /**
     * @var \Licentia\Panda\Model\ResourceModel\Subscribers\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var \Magento\Framework\View\Model\PageLayout\Config\BuilderInterface
     */
    protected $pageLayoutBuilder;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $registry = null;

    /**
     * Log constructor.
     *
     * @param \Magento\Backend\Block\Template\Context                          $context
     * @param \Magento\Backend\Helper\Data                                     $backendHelper
     * @param \Licentia\Import\Model\ResourceModel\Log\CollectionFactory       $collectionFactory
     * @param \Magento\Framework\View\Model\PageLayout\Config\BuilderInterface $pageLayoutBuilder
     * @param \Magento\Framework\Registry                                      $registry
     * @param array                                                            $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Licentia\Import\Model\ResourceModel\Log\CollectionFactory $collectionFactory,
        \Magento\Framework\View\Model\PageLayout\Config\BuilderInterface $pageLayoutBuilder,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {

        $this->registry = $registry;
        $this->collectionFactory = $collectionFactory;
        $this->pageLayoutBuilder = $pageLayoutBuilder;
        parent::__construct($context, $backendHelper, $data);
    }

    protected function _construct()
    {

        parent::_construct();
        $this->setId('pandaLogeGrid');
        $this->setDefaultSort('log_id');
        $this->setDefaultDir('ASC');
    }

    /**
     * Prepare collection
     *
     * @return \Magento\Backend\Block\Widget\Grid
     */
    protected function _prepareCollection()
    {

        $collection = $this->collectionFactory->create();
        /** @var \Licentia\Import\Model\Import $import */
        $import = $this->registry->registry('panda_import');
        $collection->addFieldToFilter('import_id', $import->getId());

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Prepare columns
     *
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     * @throws \Exception
     */
    protected function _prepareColumns()
    {

        $this->addColumn(
            'log_id',
            [
                'header' => __('ID'),
                'align'  => 'right',
                'width'  => '50px',
                'index'  => 'log_id',
            ]
        );

        $this->addColumn(
            'imported_at',
            [
                'header'    => __('Import Date'),
                'align'     => 'left',
                'index'     => 'imported_at',
                'width'     => '170px',
                'type'      => 'datetime',
                'gmtoffset' => true,
            ]
        );

        $this->addColumn(
            'created',
            [
                'header' => __('Records created'),
                'align'  => 'left',
                'index'  => 'created',
            ]
        );

        $this->addColumn(
            'updated',
            [
                'header' => __('Records updated'),
                'align'  => 'left',
                'index'  => 'updated',
            ]
        );

        $this->addColumn(
            'deleted',
            [
                'header' => __('Records delete'),
                'align'  => 'left',
                'index'  => 'deleted',
            ]
        );

        $this->addColumn(
            'message',
            [
                'header' => __('Message'),
                'align'  => 'left',
                'index'  => 'message',
            ]
        );

        $this->addColumn(
            'result',
            [
                'header'  => __('Result'),
                'align'   => 'left',
                'width'   => '150px',
                'index'   => 'result',
                'type'    => 'options',
                'options' => [
                    'success'         => __('Success'),
                    'success_no_file' => __('Success (No File)'),
                    'fail'            => __('Fail'),
                ],
            ]
        );

        return parent::_prepareColumns();
    }

}
