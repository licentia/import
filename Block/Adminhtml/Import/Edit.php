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

namespace Licentia\Import\Block\Adminhtml\Import;

/**
 * Class Edit
 *
 * @package Licentia\Import\Block\Adminhtml\Import
 */
class Edit extends \Magento\Backend\Block\Widget\Form\Container
{

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $registry = null;

    /**
     * @param \Magento\Backend\Block\Widget\Context $context
     * @param \Magento\Framework\Registry           $registry
     * @param array                                 $data
     */
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {

        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    protected function _construct()
    {

        $this->_blockGroup = 'Licentia_Import';
        $this->_controller = 'adminhtml_import';

        /** @var \Licentia\Import\Model\Import $import */
        $import = $this->registry->registry('panda_import');

        parent::_construct();

        if (!$import->getId()) {
            $this->buttonList->remove('reset');
        }


        $this->buttonList->remove('save');
        $this->getToolbar()
             ->addChild(
                 'save-split-button-',
                 'Magento\Backend\Block\Widget\Button\SplitButton',
                 [
                     'id'           => 'save-split-button',
                     'label'        => __('Save Import'),
                     'class_name'   => 'Magento\Backend\Block\Widget\Button\SplitButton',
                     'button_class' => 'widget-button-update',
                     'options'      => [
                         [
                             'id'             => 'save-button',
                             'label'          => __('Save Import'),
                             'default'        => true,
                             'data_attribute' => [
                                 'mage-init' => [
                                     'button' => [
                                         'event'  => 'saveAndContinueEdit',
                                         'target' => '#edit_form',
                                     ],
                                 ],
                             ],
                         ],
                         [
                             'id'             => 'save-continue-button',
                             'label'          => __('Save Import & Close'),
                             'data_attribute' => [
                                 'mage-init' => [
                                     'button' => [
                                         'event'  => 'save',
                                         'target' => '#edit_form',
                                     ],
                                 ],
                             ],
                         ],
                     ],
                 ]
             );

        $location = $this->getUrl(
            '*/*/delete',
            [
                'eid'    => $this->getRequest()->getParam('eid'),
                'tab_id' => 'element_section',
            ]
        );
        $locationReturn = $this->getUrl(
            '*/*/edit',
            [
                'id'     => $this->getRequest()->getParam('id'),
                'tab_id' => 'element_section',
            ]
        );

        $confirm = __('Are you sure?');

        $this->buttonList->update('delete', 'onclick', "deleteConfirm('{$confirm}','{$location}')");
        $this->buttonList->update('back', 'onclick', "setLocation('{$locationReturn}')");

        if (!$this->getRequest()->getParam('eid')) {
            $this->buttonList->remove('delete');
        }
    }

    /**
     * @return string
     */
    protected function _getSaveAndContinueUrl()
    {

        return $this->getUrl(
            '*/*/save',
            ['_current' => true, 'back' => 'edit', 'tab' => '{{tab_id}}']
        );
    }

    /**
     * Get edit form container header text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getHeaderText()
    {

        if ($import = $this->registry->registry('panda_import')->getId()) {
            return __("Edit Import '%1'", $this->escapeHtml($import->getName()));
        } else {
            return __('New Import');
        }
    }
}
