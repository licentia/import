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

namespace Licentia\Import\Ui\Component\Listing;

/**
 * Class Columns
 *
 * @package Licentia\Import\Ui\Component\Listing
 */
class Columns extends \Magento\Ui\Component\Listing\Columns
{

    /**
     * Default columns max order
     */
    const DEFAULT_COLUMNS_MAX_ORDER = 100;

    /**
     * @var array
     */
    protected $filterMap = [
        'default' => 'text',
        'date'    => 'dateRange',
    ];

    /**
     * @var \Licentia\Import\Ui\Component\ColumnFactory
     */
    protected $columnFactory;

    /**
     * @var \Licentia\Panda\Model\ResourceModel\ExtraFields\CollectionFactory
     */
    protected $extraFieldsCollection;

    /**
     * Columns constructor.
     *
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface      $context
     * @param \Licentia\Import\Ui\Component\ColumnFactory                        $columnFactory
     * @param \Licentia\Panda\Model\ResourceModel\ExtraFields\CollectionFactory $extraFieldsCollection
     * @param array                                                             $components
     * @param array                                                             $data
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Licentia\Import\Ui\Component\ColumnFactory $columnFactory,
        \Licentia\Panda\Model\ResourceModel\ExtraFields\CollectionFactory $extraFieldsCollection,
        array $components = [],
        array $data = []
    ) {

        parent::__construct($context, $components, $data);
        $this->columnFactory = $columnFactory;
        $this->extraFieldsCollection = $extraFieldsCollection;
    }

    public function prepare()
    {

        $collection = $this->extraFieldsCollection->create();

        $columnSortOrder = self::DEFAULT_COLUMNS_MAX_ORDER;
        foreach ($collection as $attribute) {
            $config = [];
            if (!isset($this->components['field_' . $attribute->getEntryCode()])) {
                $config['sortOrder'] = ++$columnSortOrder;
                $config['add_field'] = false;
                $config['visible'] = true;
                $config['filter'] = $this->getFilterType($attribute->getType());
                $column = $this->columnFactory->create($attribute, $this->getContext(), $config);
                $column->prepare();
                $this->addComponent('field_' . $attribute->getEntryCode(), $column);
            }
        }

        parent::prepare();
    }

    /**
     * Retrieve filter type by $frontendInput
     *
     * @param string $frontendInput
     *
     * @return string
     */
    protected function getFilterType($frontendInput)
    {

        return isset($this->filterMap[$frontendInput]) ? $this->filterMap[$frontendInput] : $this->filterMap['default'];
    }
}
