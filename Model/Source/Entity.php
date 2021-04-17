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

namespace Licentia\Import\Model\Source;

/**
 * Class Entity
 *
 * @package Licentia\Import\Model
 */
class Entity implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * @var \Magento\ImportExport\Model\Import\ConfigInterface
     */
    protected \Magento\ImportExport\Model\Import\ConfigInterface $_importConfig;

    /**
     * @param \Magento\ImportExport\Model\Import\ConfigInterface $importConfig
     */
    public function __construct(\Magento\ImportExport\Model\Import\ConfigInterface $importConfig)
    {

        $this->_importConfig = $importConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {

        $options = [];
        $options[] = ['label' => __('-- Please Select --'), 'value' => ''];
        foreach ($this->_importConfig->getEntities() as $entityName => $entityConfig) {
            $options[] = ['label' => __($entityConfig['label']), 'value' => $entityName];
        }

        return $options;
    }
}
