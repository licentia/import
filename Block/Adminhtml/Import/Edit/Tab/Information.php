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
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;

/**
 * Class Information
 *
 * @package Licentia\Import\Block\Adminhtml\Import\Edit\Tab
 */
class Information extends \Magento\Backend\Block\Widget\Form\Generic
{

    /**
     * @var \Licentia\Import\Helper\Data
     */
    protected $pandaHelper;

    /**
     * @var \Magento\Store\Model\System\Store
     */
    protected $systemStore;

    /**
     * @var \Licentia\Import\Model\ImportFactory
     */
    protected $importFactory;

    /**
     * Basic import model
     *
     * @var Import
     */
    protected $_importModel;

    /**
     * @var \Magento\ImportExport\Model\Source\Import\EntityFactory
     */
    protected $_entityFactory;

    /**
     * @var \Magento\ImportExport\Model\Source\Import\Behavior\Factory
     */
    protected $_behaviorFactory;

    /**
     * @var \Magento\Config\Model\Config\Source\Email\Identity
     */
    protected $emailIdentity;

    /**
     * @var \Magento\Config\Model\Config\Source\Email\Template
     */
    protected $emailTemplate;

    /**
     * @var Import\ImageDirectoryBaseProvider
     */
    private $imagesDirectoryProvider;

    /**
     * Main constructor.
     *
     * @param \Magento\Config\Model\Config\Source\Email\Template         $emailTemplate
     * @param \Magento\Config\Model\Config\Source\Email\Identity         $emailIdentity
     * @param \Magento\ImportExport\Model\Source\Import\EntityFactory    $entityFactory
     * @param \Magento\Backend\Block\Template\Context                    $context
     * @param \Magento\Framework\Registry                                $registry
     * @param \Magento\Framework\Data\FormFactory                        $formFactory
     * @param \Licentia\Panda\Helper\Data                                $pandaHelper
     * @param \Magento\Store\Model\System\Store                          $systemStore
     * @param \Licentia\Import\Model\ImportFactory                       $importFactory
     * @param Import                                                     $importModel
     * @param \Magento\ImportExport\Model\Source\Import\Behavior\Factory $behaviorFactory
     * @param array                                                      $data
     */
    public function __construct(
        \Magento\Config\Model\Config\Source\Email\Template $emailTemplate,
        \Magento\Config\Model\Config\Source\Email\Identity $emailIdentity,
        \Magento\ImportExport\Model\Source\Import\EntityFactory $entityFactory,
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Licentia\Panda\Helper\Data $pandaHelper,
        \Magento\Store\Model\System\Store $systemStore,
        \Licentia\Import\Model\ImportFactory $importFactory,
        \Magento\ImportExport\Model\Import $importModel,
        \Magento\ImportExport\Model\Source\Import\Behavior\Factory $behaviorFactory,
        array $data = []
    ) {

        parent::__construct($context, $registry, $formFactory, $data);

        $this->emailTemplate = $emailTemplate;
        $this->emailIdentity = $emailIdentity;
        $this->pandaHelper = $pandaHelper;
        $this->importFactory = $importFactory;
        $this->systemStore = $systemStore;
        $this->_entityFactory = $entityFactory;
        $this->_entityFactory = $entityFactory;
        $this->_behaviorFactory = $behaviorFactory;
        parent::__construct($context, $registry, $formFactory, $data);
        $this->_importModel = $importModel;
        $this->imagesDirectoryProvider = $imageDirProvider
                                         ?? ObjectManager::getInstance()->get(Import\ImageDirectoryBaseProvider::class);
    }

    /**
     * @return $this
     */
    protected function _prepareForm()
    {

        $manageForm = $this->importFactory->create();

        /** @var \Licentia\Import\Model\Import $current */
        $current = $this->_coreRegistry->registry('panda_import');

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create(
            [
                'data' => [
                    'id'     => 'edit_form',
                    'action' => $this->getData('action'),
                    'method' => 'post',
                ],
            ]
        );
        $fieldset = $form->addFieldset('content_fieldset', ['legend' => __('Content')]);

        $fieldset->addField(
            'name',
            'text',
            [
                'name'     => 'name',
                'label'    => __('Name'),
                'title'    => __('Name'),
                "required" => true,
            ]
        );

        $fieldset->addField(
            'description',
            'textarea',
            [
                'name'     => 'name',
                'label'    => __('Description'),
                'title'    => __('Description'),
                "required" => true,
            ]
        );

        $fieldset->addField(
            'is_active',
            "select",
            [
                "label"   => __('Status'),
                "options" => ['1' => __('Active'), '0' => __('Inactive')],
                "name"    => 'is_active',
            ]
        );

        $fieldset->addField(
            'entity',
            'select',
            [
                'name'     => 'entity',
                'title'    => __('Entity Type'),
                'label'    => __('Entity Type'),
                'required' => true,
                'values'   => $this->_entityFactory->create()->toOptionArray(),
            ]
        );

        $fieldset->addField(
            Import::FIELD_NAME_ALLOWED_ERROR_COUNT,
            'text',
            [
                'name'     => Import::FIELD_NAME_ALLOWED_ERROR_COUNT,
                'label'    => __('Allowed Errors Count'),
                'title'    => __('Allowed Errors Count'),
                'required' => true,
                'value'    => 10,
                'class'    => ' validate-number validate-greater-than-zero input-text',
                'note'     => __(
                    'Please specify number of errors to halt import process'
                ),
            ]
        );

        $fieldset->addField(
            Import::FIELD_FIELD_SEPARATOR,
            'text',
            [
                'name'     => Import::FIELD_FIELD_SEPARATOR,
                'label'    => __('Field separator'),
                'title'    => __('Field separator'),
                'required' => true,
                'value'    => ',',
            ]
        );

        $fieldset->addField(
            Import::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR,
            'text',
            [
                'name'     => Import::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR,
                'label'    => __('Multiple value separator'),
                'title'    => __('Multiple value separator'),
                'required' => true,
                'value'    => Import::DEFAULT_GLOBAL_MULTI_VALUE_SEPARATOR,
            ]
        );

        $fieldset->addField(
            Import::FIELD_EMPTY_ATTRIBUTE_VALUE_CONSTANT,
            'text',
            [
                'name'     => Import::FIELD_EMPTY_ATTRIBUTE_VALUE_CONSTANT,
                'label'    => __('Empty attribute value constant'),
                'title'    => __('Empty attribute value constant'),
                'required' => true,
                'value'    => Import::DEFAULT_EMPTY_ATTRIBUTE_VALUE_CONSTANT,
            ]
        );

        $fieldset->addField(
            Import::FIELDS_ENCLOSURE,
            'checkbox',
            [
                'name'  => Import::FIELDS_ENCLOSURE,
                'label' => __('Fields enclosure'),
                'title' => __('Fields enclosure'),
                'value' => 1,
            ]
        );

        $fieldset2 = $form->addFieldset('file_fieldset', ['legend' => __('Files')]);

        $fieldset2->addField(
            'server_type',
            "select",
            [
                "label"   => __('Server Type'),
                "options" => ['local' => __('Local'), 'remote' => __('Remote (SSH)')],
                "name"    => 'server_type',
            ]
        );

        $fieldset2->addField(
            'file_directory',
            'text',
            [
                'name'     => 'file_directory',
                'label'    => __('File Directory'),
                'title'    => __('File Directory'),
                'required' => true,
                'class'    => 'input-text',
            ]
        );

        $fieldset2->addField(
            'file_name',
            'text',
            [
                'name'     => 'file_name',
                'label'    => __('File Name'),
                'title'    => __('File Name'),
                'required' => true,
                'class'    => 'input-text',
            ]
        );

        $fieldset2->addField(
            Import::FIELD_NAME_IMG_FILE_DIR,
            'text',
            [
                'name'     => Import::FIELD_NAME_IMG_FILE_DIR,
                'label'    => __('Images File Directory'),
                'title'    => __('Images File Directory'),
                'required' => false,
                'class'    => 'input-text',
                'note'     => __(
                    $this->escapeHtml(
                        'For Type "Local Server" use relative path to &lt;Magento root directory&gt;/'
                        . $this->imagesDirectoryProvider->getDirectoryRelativePath()
                        . ', e.g. <i>product_images</i>, <i>import_images/batch1</i>.<br><br>'
                        . 'For example, in case <i>product_images</i>, files should be placed into '
                        . '<i>&lt;Magento root directory&gt;/'
                        . $this->imagesDirectoryProvider->getDirectoryRelativePath() . '/product_images</i> folder.',
                        ['i', 'br']
                    )
                ),
            ]
        );

        $fieldset2->addField(
            'ftp_host',
            'text',
            [
                'name'     => 'ftp_host',
                'label'    => __('FTP Host'),
                'title'    => __('FTP Host'),
                'required' => true,
                'class'    => 'input-text',
            ]
        );

        $fieldset2->addField(
            'ftp_username',
            'text',
            [
                'name'     => 'ftp_username',
                'label'    => __('FTP Username'),
                'title'    => __('FTP Username'),
                'required' => true,
                'class'    => 'input-text',
            ]
        );

        $fieldset2->addField(
            'ftp_password',
            'password',
            [
                'name'     => 'ftp_password',
                'label'    => __('FTP Password'),
                'title'    => __('FTP Password'),
                'required' => true,
                'class'    => 'input-text',
            ]
        );

        $fieldset2->addField(
            'ftp_file_mode',
            "select",
            [
                "label"   => __('File Mode'),
                "options" => ['binary' => __('Binary'), 'ascii' => __('ASCII')],
                "name"    => 'ftp_file_mode',
            ]
        );

        $fieldset2->addField(
            'ftp_passive_mode',
            "select",
            [
                "label"   => __('Passive Mode'),
                "options" => ['1' => __('Yes'), '0' => __('No')],
                "name"    => 'ftp_passive_mode',
            ]
        );

        $fieldset3 = $form->addFieldset('file_email', ['legend' => __('Email Notifications')]);

        $fieldset3->addField(
            'failed_email_sender',
            "select",
            [
                "label"  => __('Failed Email Sender'),
                "values" => $this->emailIdentity->toOptionArray(),
                "name"   => 'failed_email_sender',
            ]
        );

        $fieldset3->addField(
            'failed_email_receiver',
            "select",
            [
                "label"  => __('Failed Email Receiver'),
                "values" => $this->emailIdentity->toOptionArray(),
                "name"   => 'failed_email_receiver',
            ]
        );

        $fieldset3->addField(
            'failed_email_template',
            "select",
            [
                "label"  => __('Failed Email Template'),
                "values" => $this->emailTemplate->setData('path','panda_import_failure_template')->toOptionArray(),
                "name"   => 'failed_email_template',
            ]
        );

        $fieldset3->addField(
            'failed_email_copy',
            'text',
            [
                'name'  => 'failed_email_copy',
                'label' => __('Failed Email Copy'),
                'title' => __('Failed Email Copy'),
                'class' => 'input-text',
            ]
        );

        $fieldset3->addField(
            'failed_email_copy_method',
            "select",
            [
                "label"   => __('Send Copy Email Method'),
                "options" => ['copy' => __('Copy'), 'bcc' => __('BCC')],
                "name"    => 'failed_email_copy_method',
            ]
        );

        $this->setForm($form);

        if ($current) {
            $form->addValues($current->getData());
        }

        return parent::_prepareForm();
    }
}
