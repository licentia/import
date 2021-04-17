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
    protected \Licentia\Import\Helper\Data $importHelper;

    /**
     * @var \Magento\Store\Model\System\Store
     */
    protected \Magento\Store\Model\System\Store $systemStore;

    /**
     * @var \Licentia\Import\Model\ImportFactory
     */
    protected \Licentia\Import\Model\ImportFactory $importFactory;

    /**
     * @var \Magento\ImportExport\Model\Source\Import\EntityFactory
     */
    protected \Magento\ImportExport\Model\Source\Import\EntityFactory $_entityFactory;

    /**
     * @var \Magento\ImportExport\Model\Source\Import\Behavior\Factory
     */
    protected \Magento\ImportExport\Model\Source\Import\Behavior\Factory $_behaviorFactory;

    /**
     * @var \Magento\Config\Model\Config\Source\Email\Identity
     */
    protected \Magento\Config\Model\Config\Source\Email\Identity $emailIdentity;

    /**
     * @var \Magento\Config\Model\Config\Source\Email\Template
     */
    protected \Magento\Config\Model\Config\Source\Email\Template $emailTemplate;

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
        \Licentia\Import\Helper\Data $importHelper,
        \Magento\Store\Model\System\Store $systemStore,
        \Licentia\Import\Model\ImportFactory $importFactory,
        \Magento\ImportExport\Model\Source\Import\Behavior\Factory $behaviorFactory,
        array $data = []
    ) {

        parent::__construct($context, $registry, $formFactory, $data);

        $this->emailTemplate = $emailTemplate;
        $this->emailIdentity = $emailIdentity;
        $this->importHelper = $importHelper;
        $this->importFactory = $importFactory;
        $this->systemStore = $systemStore;
        $this->_entityFactory = $entityFactory;
        $this->_entityFactory = $entityFactory;
        $this->_behaviorFactory = $behaviorFactory;
        parent::__construct($context, $registry, $formFactory, $data);
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
                'name'     => 'description',
                'label'    => __('Description'),
                'title'    => __('Description'),
                "required" => true,
            ]
        );

        $fieldset->addField(
            'is_active',
            "select",
            [
                "label"   => __('Is Active'),
                "options" => ['1' => __('Yes'), '0' => __('No')],
                "name"    => 'is_active',
            ]
        );

        $fieldset->addField(
            'entity_type',
            'select',
            [
                'name'     => 'entity_type',
                'title'    => __('Entity Type'),
                'label'    => __('Entity Type'),
                'required' => true,
                'values'   => $this->_entityFactory->create()->toOptionArray(),
            ]
        );

        $fieldset->addField(
            'import_behavior',
            'select',
            [
                'name'     => 'import_behavior',
                'title'    => __('Import Behavior'),
                'label'    => __('Import Behavior'),
                'required' => true,
                "options"  => [
                    'append'  => __('Add/Update'),
                    'replace' => __('Replace'),
                    'delete'  => __('Delete'),
                ],
            ]
        );

        $fieldset->addField(
            'on_error',
            "select",
            [
                "label"   => __('On Errors'),
                "options" => [
                    'validation-stop-on-errors' => __('Stop Processing'),
                    'validation-skip-errors'    => __('Continue Processing'),
                ],
                "name"    => 'on_error',
            ]
        );
        $html = '
                <script type="text/javascript">

                require(["jquery"],function ($){
                toggleControlsValidateCron = {
                    run: function() {
                        if($("#cron").val() == "other"){
                            $("div.admin__field.field.field-cron_expression").show();
                        }else{ 
                            $("div.admin__field.field.field-cron_expression").hide();
                        }
                    }
                }
                window.toggleControlsValidateCron = toggleControlsValidateCron;
                $(function() {
                    toggleControlsValidateCron.run();
                });

                });
                </script>
                ';

        $fieldset->addField(
            'cron',
            'select',
            [
                'name'     => 'cron',
                'label'    => __('Cron Expression'),
                'title'    => __('Cron Expression'),
                "onchange" => 'toggleControlsValidateCron.run();',
                'options'  => [
                    '*/5 * * * *'  => __('Once Per Five Minutes (*/5 * * * *)'),
                    '0,30 * * * *' => __('Twice Per Hour (0,30 * * * *)'),
                    '0 * * * *'    => __('Once Per Hour (0 * * * *)'),
                    '0 0,12 * * *' => __('Twice Per Day (0 0,12 * * *)'),
                    '0 0 * * *'    => __('Once Per Day( 0 0 * * *)'),
                    '0 0 * * 0'    => __('Once Per Week (0 0 * * 0)'),
                    '0 0 1,15 * *' => __('On the 1st and 15th of the Month (0 0 1,15 * *)'),
                    '0 0 1 * *'    => __('Once Per Month (0 0 1 * *)'),
                    'other'        => __('Custom Expression'),
                ],
            ]
        )
                 ->setAfterElementHtml($html);

        $fieldset->addField(
            'cron_expression',
            'text',
            [
                'name'  => 'cron_expression',
                'label' => __('CRON Expression'),
                'title' => __('CRON Expression'),
                'class' => 'small_input',
            ]
        );

        $fieldset->addField(
            'after_import',
            'select',
            [
                'name'    => 'after_import',
                'title'   => __('After Import Action'),
                'label'   => __('After Import Action'),
                "options" => [
                    'archive' => __('Archive Files'),
                    'delete'  => __('Delete Files'),
                ],
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
                'class'    => 'small_input',
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
                'class'    => 'small_input',
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
                'class'    => 'small_input',
            ]
        );

        $fieldset->addField(
            Import::FIELDS_ENCLOSURE,
            "select",
            [
                'label'   => __('Fields enclosure'),
                'title'   => __('Fields enclosure'),
                "options" => ['1' => __('Yes'), '0' => __('No')],
                "name"    => Import::FIELDS_ENCLOSURE,
            ]
        );

        $fieldset2 = $form->addFieldset('file_fieldset', ['legend' => __('Files')]);
        $html = '
                <script type="text/javascript">

                require(["jquery"],function ($){
                toggleControlsValidateProtect = {
                    run: function() {
                        if($("#server_type").val() == "ftp"){
                            $("div.admin__field.field.field-ftp_host").show();
                            $("div.admin__field.field.field-ftp_port").show();
                            $("div.admin__field.field.field-ftp_username").show();
                            $("div.admin__field.field.field-ftp_password").show();
                            $("div.admin__field.field.field-ftp_file_mode").show();
                            $("div.admin__field.field.field-ftp_passive_mode").show();
                            $("div.admin__field.field.field-file_directory").show();
                            $("div.admin__field.field.field-file_name").show();
                            $("div.admin__field.field.field-import_images_file_dir").show();
                            $("div.admin__field.field.field-remote_url").hide();
                            $("div.admin__field.field.field-remote_username").hide();
                            $("div.admin__field.field.field-remote_password").hide();
                            $("div.admin__field.field.field-remote_bearer").hide();
                            
                            $("#ftp_host").addClass("required-entry");
                            $("#ftp_username").addClass("required-entry");
                            $("#ftp_password").addClass("required-entry");
                            $("#file_name").addClass("required-entry");
                            $("#remote_url").removeClass("required-entry");
                        }else if($("#server_type").val() == "ssh"){
                            $("div.admin__field.field.field-import_images_file_dir").show();
                            $("div.admin__field.field.field-file_directory").show();
                            $("div.admin__field.field.field-file_name").show();
                            $("div.admin__field.field.field-ftp_host").show();
                            $("div.admin__field.field.field-ftp_port").show();
                            $("div.admin__field.field.field-ftp_username").show();
                            $("div.admin__field.field.field-ftp_password").show();
                            $("div.admin__field.field.field-ftp_file_mode").hide();
                            $("div.admin__field.field.field-ftp_passive_mode").hide();
                            $("div.admin__field.field.field-remote_url").hide();
                            $("div.admin__field.field.field-remote_username").hide();
                            $("div.admin__field.field.field-remote_password").hide();
                            $("div.admin__field.field.field-remote_bearer").hide();
                            
                            $("#remote_url").removeClass("required-entry");
                            $("#ftp_host").addClass("required-entry");
                            $("#ftp_username").addClass("required-entry");
                            $("#ftp_password").addClass("required-entry");
                            $("#file_name").addClass("required-entry");
                        }else if($("#server_type").val() == "url"){
                            $("div.admin__field.field.field-import_images_file_dir").hide();
                            $("div.admin__field.field.field-file_directory").hide();
                            $("div.admin__field.field.field-file_name").hide();
                            $("div.admin__field.field.field-ftp_host").hide();
                            $("div.admin__field.field.field-ftp_port").hide();
                            $("div.admin__field.field.field-ftp_username").hide();
                            $("div.admin__field.field.field-ftp_password").hide();
                            $("div.admin__field.field.field-ftp_file_mode").hide();
                            $("div.admin__field.field.field-ftp_passive_mode").hide();
                            $("div.admin__field.field.field-remote_url").show();
                            $("div.admin__field.field.field-remote_username").show();
                            $("div.admin__field.field.field-remote_password").show();
                            $("div.admin__field.field.field-remote_bearer").show();
                            
                            $("#remote_url").addClass("required-entry");
                            $("#ftp_host").removeClass("required-entry");
                            $("#ftp_username").removeClass("required-entry");
                            $("#ftp_password").removeClass("required-entry");
                            $("#file_name").removeClass("required-entry");
                        }else{ 
                            $("#ftp_host").removeClass("required-entry");
                            $("#remote_url").removeClass("required-entry");
                            $("#ftp_username").removeClass("required-entry");
                            $("#ftp_password").removeClass("required-entry");
                            $("#file_name").addClass("required-entry");
                          
                            $("div.admin__field.field.field-file_directory").show();
                            $("div.admin__field.field.field-file_name").show();
                            $("div.admin__field.field.field-import_images_file_dir").show();
                            $("div.admin__field.field.field-ftp_host").hide();
                            $("div.admin__field.field.field-ftp_port").hide();
                            $("div.admin__field.field.field-ftp_username").hide();
                            $("div.admin__field.field.field-ftp_password").hide();
                            $("div.admin__field.field.field-ftp_file_mode").hide();
                            $("div.admin__field.field.field-ftp_passive_mode").hide();
                            $("div.admin__field.field.field-remote_url").hide();
                            $("div.admin__field.field.field-remote_username").hide();
                            $("div.admin__field.field.field-remote_password").hide();
                            $("div.admin__field.field.field-remote_bearer").hide();
                        }
                    }
                }
                window.toggleControlsValidateProtect = toggleControlsValidateProtect;
                $(function() {
                    toggleControlsValidateProtect.run();
                });

                });
                </script>
                ';

        $fieldset2->addField(
            'server_type',
            "select",
            [
                "label"    => __('Server Type'),
                "options"  => [
                    'local' => __('Local'),
                    'ftp'   => __('Remote FTP'),
                    'ssh'   => __('Remote SFTP'),
                    'url'   => __('URL'),
                ],
                "name"     => 'server_type',
                "onchange" => 'toggleControlsValidateProtect.run();',
            ]
        )
                  ->setAfterElementHtml($html);

        $fieldset2->addField(
            'file_directory',
            'text',
            [
                'name'     => 'file_directory',
                'label'    => __('File Directory'),
                'title'    => __('File Directory'),
                'required' => true,
                'value'    => 'var/importexport',
                'class'    => 'input-text',
                'note'     => __('For Type "Local" use relative path to Magento installation, ' .
                                 'e.g. var/export, var/import, var/export/some/dir<br><br>For "Remote" use the full ' .
                                 'path, e.g. /home/user/uploads/'),
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
                'label'    => __('Host'),
                'title'    => __('Host'),
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
            'remote_url',
            'text',
            [
                'name'     => 'remote_url',
                'label'    => __('URL'),
                'title'    => __('URL'),
                'class'    => 'input-text',
                'required' => true,
            ]
        );

        $fieldset2->addField(
            'remote_username',
            'text',
            [
                'name'  => 'remote_username',
                'label' => __('Basic Auth Username'),
                'title' => __('Basic Auth Username'),
                'class' => 'input-text',
            ]
        );

        $fieldset2->addField(
            'remote_password',
            'password',
            [
                'name'  => 'remote_password',
                'label' => __('Basic Auth Password'),
                'title' => __('Basic Auth Password'),
                'class' => 'input-text',
            ]
        );

        $fieldset2->addField(
            'remote_bearer',
            'password',
            [
                'name'  => 'remote_bearer',
                'label' => __('Auth Bearer'),
                'title' => __('Auth Bearer'),
                'class' => 'input-text',
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
            'ftp_port',
            'text',
            [
                'name'  => 'ftp_port',
                'label' => __('Remote Port'),
                'title' => __('Remote Password'),
                'class' => 'input-text validate-digits',
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

        $fieldset3 = $form->addFieldset('file_email', ['legend' => __('Failure Email Notifications')]);

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
            'failed_email_recipient',
            "text",
            [
                "label" => __('Failed Email Recipient'),
                "name"  => 'failed_email_recipient',
                "note"  => __('Separate multiple using commas'),
            ]
        );

        $fieldset3->addField(
            'failed_email_copy_method',
            "select",
            [
                "label"   => __('Send Copy Email Method'),
                "options" => ['copy' => __('Copy'), 'bcc' => __('BCC')],
                "name"    => 'failed_email_copy_method',
                'note'    => __('If multiple Recipients defined'),
            ]
        );

        $fieldset3 = $form->addFieldset('file_email_success', ['legend' => __('Success Email Notifications')]);

        $fieldset3->addField(
            'success_email_sender',
            "select",
            [
                "label"  => __('Success Email Sender'),
                "values" => $this->emailIdentity->toOptionArray(),
                "name"   => 'success_email_sender',
            ]
        );

        $fieldset3->addField(
            'success_email_recipient',
            "text",
            [
                "label" => __('Success Email Recipient'),
                "name"  => 'success_email_recipient',
                "note"  => __('Separate multiple using commas'),
            ]
        );

        $fieldset3->addField(
            'success_email_copy_method',
            "select",
            [
                "label"   => __('Send Copy Email Method'),
                "options" => ['copy' => __('Copy'), 'bcc' => __('BCC')],
                "name"    => 'success_email_copy_method',
                'note'    => __('If multiple Recipients defined'),
            ]
        );

        $this->setForm($form);

        if ($current) {
            $form->addValues($current->getData());
        }

        return parent::_prepareForm();
    }
}
