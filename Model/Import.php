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

namespace Licentia\Import\Model;

/**
 * Class Import
 *
 * @package Licentia\Import\Model
 */
class Import extends \Magento\Framework\Model\AbstractModel
{

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'panda_import';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    protected $_eventObject = 'panda_import';

    /**
     * @var \Licentia\Import\Helper\Data
     */
    protected $pandaHelper;

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {

        $this->_init(ResourceModel\Import::class);
    }

    /**
     * @return array
     */
    public function toFormValues()
    {

        $values = $this->toOptionArray();

        $return = [];
        foreach ($values as $rule) {
            $return[$rule['value']] = $rule['label'];
        }

        return $return;
    }

    /**
     * @param bool $first
     *
     * @return array
     */
    public function toOptionArray($first = false)
    {

        $collection = $this->getCollection()->addFieldToFilter('entry_type', 'frontend');

        $return = [];

        if ($first) {
            $return[] = ['value' => '0', 'label' => $first];
        }

        foreach ($collection as $item) {
            $return[] = ['value' => $item->getId(), 'label' => $item->getName()];
        }

        return $return;
    }

}
