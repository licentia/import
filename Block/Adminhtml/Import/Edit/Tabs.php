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

namespace Licentia\Import\Block\Adminhtml\Import\Edit;

/**
 * Class Tabs
 *
 * @package Licentia\Import\Block\Adminhtml\Import\Edit
 */
class Tabs extends \Magento\Backend\Block\Widget\Tabs
{

    protected function _construct()
    {

        parent::_construct();
        $this->setId('import_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Form Information'));
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function _beforeToHtml()
    {

        $this->addTab(
            'information_section',
            [
                'label'   => __('Information'),
                'title'   => __('Information'),
                'content' => $this->getLayout()
                                  ->createBlock('Licentia\Import\Block\Adminhtml\Import\Edit\Tab\Information')
                                  ->toHtml(),
            ]
        );

        return parent::_beforeToHtml();
    }
}
