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

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\ImportExport\Model\Import as MagentoImport;

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
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var MagentoImport
     */
    protected $importModel;

    /**
     * @var MagentoImport\ImageDirectoryBaseProvider|mixed
     */
    protected $imagesDirProvider;

    /**
     * @var \Magento\Cron\Model\Schedule
     */
    protected $schedule;

    public function __construct(
        \Magento\Cron\Model\Schedule $schedule,
        MagentoImport $importModel,
        \Magento\Framework\Filesystem $filesystem,
        \Licentia\Panda\Helper\Data $pandaHelper,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        ?\Magento\ImportExport\Model\Import\ImageDirectoryBaseProvider $imageDirectoryBaseProvider = null
    ) {

        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->schedule = $schedule;
        $this->importModel = $importModel;
        $this->filesystem = $filesystem;
        $this->pandaHelper = $pandaHelper;
        $this->imagesDirProvider = $imageDirectoryBaseProvider
                                   ?? ObjectManager::getInstance()
                                                   ->get(\Magento\ImportExport\Model\Import\ImageDirectoryBaseProvider::class);
    }

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
     * @return $this
     */
    protected function _afterLoad()
    {

        parent::_afterLoad();

        if ($this->getFtpPassword()) {
            $this->setFtpPassword($this->pandaHelper->getEncryptor()->decrypt($this->getFtpPassword()));
        }

        return $this;
    }

    /**
     *
     */
    public function beforeSave()
    {

        parent::beforeSave();

        if ($this->getFtpPassword()) {
            $this->setFtpPassword($this->pandaHelper->getEncryptor()->encrypt($this->getFtpPassword()));
        }
    }

    /**
     * @return Import
     */
    public function validateBeforeSave()
    {

        $this->schedule->setCronExpr($this->getCron());
        $this->schedule->setScheduledAt(date('Y-m-d H:i:s'));
        $this->schedule->trySchedule();

        return parent::validateBeforeSave();
    }

    /**
     * @return string|null
     */
    public function getFullFilePathName()
    {

        $localFile = null;

        if ($this->getServerType() == 'local') {
            $fileDir = $this->getFileDirectory();
            $fileName = $fileDir . $this->getFileName();

            $exists = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)
                                       ->isFile($fileName);

            $localFile = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)
                                          ->getAbsolutePath($fileName);
        }

        if ($this->getServerType() == 'ftp') {

            $fileDir = $this->getFileDirectory();
            $fileName = $fileDir . $this->getFileName();
            $archiveDir = $fileDir . 'archives/';

            $localFile = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)
                                          ->getAbsolutePath('importexport/' . $this->getEntityType() . '.csv');

            $mediaDir = $this->imagesDirProvider->getDirectory()->getAbsolutePath();

            $binary = $this->getFtpFileMode() == 'binary' ? FTP_BINARY : FTP_ASCII;
            $connId = ftp_connect($this->getFtpHost());
            if (!@ftp_login($connId, $this->getFtpUsername(), $this->getFtpPassword())) {
                throw new \Magento\Framework\Exception\LocalizedException(__("Can't connect to FTP"));
            }

            if ($this->getFtpPassiveMode()) {
                ftp_pasv($connId, true);
            }

            if ($this->getAfterImport() == 'archive') {

                if (!@ftp_mlsd($connId, $archiveDir)) {
                    ftp_mkdir($connId, $archiveDir);
                }

                if (!@ftp_mlsd($connId, $this->getImportImagesFileDir() . 'archives/')) {
                    ftp_mkdir($connId, $this->getImportImagesFileDir() . 'archives/');
                }

            }

            $images = ftp_mlsd($connId, $this->getImportImagesFileDir());

            foreach ($images as $image) {

                if ($image['type'] != 'file') {
                    continue;
                }

                $type = pathinfo($image['name'], PATHINFO_EXTENSION);

                if (!in_array(strtolower($type), ['png', 'jpg', 'jpeg'])) {
                    continue;
                }

                ftp_get($connId, $mediaDir . $image['name'], $this->getImportImagesFileDir() . $image['name'],
                    $binary);

                if ($this->getAfterImport() == 'archive') {
                    ftp_rename($connId, $this->getImportImagesFileDir() . $image['name'],
                        $this->getImportImagesFileDir() . 'archives/' . $image['name']);
                }
                if ($this->getAfterImport() == 'delete') {
                    ftp_delete($connId, $this->getImportImagesFileDir() . $image['name']);
                }
            }

            ftp_get($connId, $localFile, $fileName, $binary);

            if ($this->getAfterImport() == 'archive') {
                ftp_rename($connId, $fileName, $archiveDir . $this->getFileName());
            }

            if ($this->getAfterImport() == 'delete') {
                ftp_delete($connId, $fileName);
            }

            ftp_close($connId);
        }

        return $localFile;
    }

    /**
     *
     */
    public function run()
    {

        $fullFileNamePath = $this->getFullFilePathName();

        $data = $this->getData();
        $data['behavior'] = $this->getImportBehavior();
        $data['entity'] = $this->getEntityType();
        $data['validation_strategy'] = $this->getOnError();
        $data['_import_field_separator'] = $this->getImportFieldSeparator();
        $data['_import_multiple_value_separator'] = $this->getImportMultipleValueSeparator();
        $data['_import_empty_attribute_value_constant'] = $this->getImportEmptyAttributeValueConstant();
        $data['import_images_file_dir'] = $this->getImportImagesFileDir();
        $data['import_file'] = $fullFileNamePath;

        $this->importModel->setData($data);
        $this->importModel->setData('images_base_directory', $this->imagesDirProvider->getDirectory());
        $errorAggregator = $this->importModel->getErrorAggregator();
        $errorAggregator->initValidationStrategy(
            $this->importModel->getData(MagentoImport::FIELD_NAME_VALIDATION_STRATEGY),
            $this->importModel->getData(MagentoImport::FIELD_NAME_ALLOWED_ERROR_COUNT)
        );

        try {
            $this->importModel->importSource();
        } catch (\Exception $e) {
            echo $e->getMessage();
            die();
        }

        if (!$this->importModel->getErrorAggregator()->hasToBeTerminated()) {
            $this->importModel->invalidateIndex();
        }

        $this->setLastExecuted($this->pandaHelper->gmtDateTime());

        $this->save();

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

        $collection = $this->getCollection();

        $return = [];

        if ($first) {
            $return[] = ['value' => '0', 'label' => $first];
        }

        foreach ($collection as $item) {
            $return[] = ['value' => $item->getId(), 'label' => $item->getName()];
        }

        return $return;
    }

    /**
     * @param $recordId
     *
     * @return $this
     */
    public function setRecordId($recordId)
    {

        return $this->setData('record_id', $recordId);
    }

    /**
     * @param $name
     *
     * @return $this
     */
    public function setName($name)
    {

        return $this->setData('name', $name);
    }

    /**
     * @param $description
     *
     * @return $this
     */
    public function setDescription($description)
    {

        return $this->setData('description', $description);
    }

    /**
     * @param $entityType
     *
     * @return $this
     */
    public function setEntityType($entityType)
    {

        return $this->setData('entity_type', $entityType);
    }

    /**
     * @param $importBehavior
     *
     * @return $this
     */
    public function setImportBehavior($importBehavior)
    {

        return $this->setData('import_behavior', $importBehavior);
    }

    /**
     * @param $onError
     *
     * @return $this
     */
    public function setOnError($onError)
    {

        return $this->setData('on_error', $onError);
    }

    /**
     * @param $isActive
     *
     * @return $this
     */
    public function setIsActive($isActive)
    {

        return $this->setData('is_active', $isActive);
    }

    /**
     * @param $fieldSeparator
     *
     * @return $this
     */
    public function setFieldSeparator($fieldSeparator)
    {

        return $this->setData('field_separator', $fieldSeparator);
    }

    /**
     * @param $fieldsEnclosure
     *
     * @return $this
     */
    public function setFieldsEnclosure($fieldsEnclosure)
    {

        return $this->setData('fields_enclosure', $fieldsEnclosure);
    }

    /**
     * @param $ImportFieldSeparator
     *
     * @return $this
     */
    public function setImportFieldSeparator($ImportFieldSeparator)
    {

        return $this->setData('_import_field_separator', $ImportFieldSeparator);
    }

    /**
     * @param $ImportEmptyAttributeValueConstant
     *
     * @return $this
     */
    public function setImportEmptyAttributeValueConstant($ImportEmptyAttributeValueConstant)
    {

        return $this->setData('_import_empty_attribute_value_constant', $ImportEmptyAttributeValueConstant);
    }

    /**
     * @param $ImportMultipleValueSeparator
     *
     * @return $this
     */
    public function setImportMultipleValueSeparator($ImportMultipleValueSeparator)
    {

        return $this->setData('_import_multiple_value_separator', $ImportMultipleValueSeparator);
    }

    /**
     * @param $serverType
     *
     * @return $this
     */
    public function setServerType($serverType)
    {

        return $this->setData('server_type', $serverType);
    }

    /**
     * @param $fileDirectory
     *
     * @return $this
     */
    public function setFileDirectory($fileDirectory)
    {

        return $this->setData('file_directory', $fileDirectory);
    }

    /**
     * @param $fileName
     *
     * @return $this
     */
    public function setFileName($fileName)
    {

        return $this->setData('file_name', $fileName);
    }

    /**
     * @param $importImagesFileDir
     *
     * @return $this
     */
    public function setImportImagesFileDir($importImagesFileDir)
    {

        return $this->setData('import_images_file_dir', $importImagesFileDir);
    }

    /**
     * @param $ftpHost
     *
     * @return $this
     */
    public function setFtpHost($ftpHost)
    {

        return $this->setData('ftp_host', $ftpHost);
    }

    /**
     * @param $ftpUsername
     *
     * @return $this
     */
    public function setFtpUsername($ftpUsername)
    {

        return $this->setData('ftp_username', $ftpUsername);
    }

    /**
     * @param $ftpPassword
     *
     * @return $this
     */
    public function setFtpPassword($ftpPassword)
    {

        return $this->setData('ftp_password', $ftpPassword);
    }

    /**
     * @param $ftpFileMode
     *
     * @return $this
     */
    public function setFtpFileMode($ftpFileMode)
    {

        return $this->setData('ftp_file_mode', $ftpFileMode);
    }

    /**
     * @param $ftpPassiveMode
     *
     * @return $this
     */
    public function setFtpPassiveMode($ftpPassiveMode)
    {

        return $this->setData('ftp_passive_mode', $ftpPassiveMode);
    }

    /**
     * @param $failedEmailReceiver
     *
     * @return $this
     */
    public function setFailedEmailReceiver($failedEmailReceiver)
    {

        return $this->setData('failed_email_receiver', $failedEmailReceiver);
    }

    /**
     * @param $failedEmailSender
     *
     * @return $this
     */
    public function setFailedEmailSender($failedEmailSender)
    {

        return $this->setData('failed_email_sender', $failedEmailSender);
    }

    /**
     * @param $failedEmailTemplate
     *
     * @return $this
     */
    public function setFailedEmailTemplate($failedEmailTemplate)
    {

        return $this->setData('failed_email_template', $failedEmailTemplate);
    }

    /**
     * @param $failedEmailCopy
     *
     * @return $this
     */
    public function setFailedEmailCopy($failedEmailCopy)
    {

        return $this->setData('failed_email_copy', $failedEmailCopy);
    }

    /**
     * @param $failedEmailCopyMethod
     *
     * @return $this
     */
    public function setFailedEmailCopyMethod($failedEmailCopyMethod)
    {

        return $this->setData('failed_email_copy_method', $failedEmailCopyMethod);
    }

    /**
     * @return mixed
     */
    public function getRecordId()
    {

        return $this->getData('record_id');
    }

    /**
     * @return mixed
     */
    public function getName()
    {

        return $this->getData('name');
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {

        return $this->getData('description');
    }

    /**
     * @return mixed
     */
    public function getEntityType()
    {

        return $this->getData('entity_type');
    }

    /**
     * @return mixed
     */
    public function getImportBehavior()
    {

        return $this->getData('import_behavior');
    }

    /**
     * @return mixed
     */
    public function getOnError()
    {

        return $this->getData('on_error');
    }

    /**
     * @return mixed
     */
    public function getIsActive()
    {

        return $this->getData('is_active');
    }

    /**
     * @return mixed
     */
    public function getFieldSeparator()
    {

        return $this->getData('field_separator');
    }

    /**
     * @return mixed
     */
    public function getFieldsEnclosure()
    {

        return $this->getData('fields_enclosure');
    }

    /**
     * @return mixed
     */
    public function getImportFieldSeparator()
    {

        return $this->getData('_import_field_separator');
    }

    /**
     * @return mixed
     */
    public function getImportEmptyAttributeValueConstant()
    {

        return $this->getData('_import_empty_attribute_value_constant');
    }

    /**
     * @return mixed
     */
    public function getImportMultipleValueSeparator()
    {

        return $this->getData('_import_multiple_value_separator');
    }

    /**
     * @return mixed
     */
    public function getServerType()
    {

        return $this->getData('server_type');
    }

    /**
     * @return mixed
     */
    public function getFileDirectory()
    {

        return rtrim($this->getData('file_directory'), '/') . '/';
    }

    /**
     * @return mixed
     */
    public function getFileName()
    {

        return $this->getData('file_name');
    }

    /**
     * @return mixed
     */
    public function getImportImagesFileDir()
    {

        return rtrim($this->getData('import_images_file_dir'), '/') . '/';
    }

    /**
     * @return mixed
     */
    public function getFtpHost()
    {

        return $this->getData('ftp_host');
    }

    /**
     * @return mixed
     */
    public function getFtpUsername()
    {

        return $this->getData('ftp_username');
    }

    /**
     * @return mixed
     */
    public function getFtpPassword()
    {

        return $this->getData('ftp_password');
    }

    /**
     * @return mixed
     */
    public function getFtpFileMode()
    {

        return $this->getData('ftp_file_mode');
    }

    /**
     * @return mixed
     */
    public function getFtpPassiveMode()
    {

        return $this->getData('ftp_passive_mode');
    }

    /**
     * @return mixed
     */
    public function getFailedEmailReceiver()
    {

        return $this->getData('failed_email_receiver');
    }

    /**
     * @return mixed
     */
    public function getFailedEmailSender()
    {

        return $this->getData('failed_email_sender');
    }

    /**
     * @return mixed
     */
    public function getFailedEmailTemplate()
    {

        return $this->getData('failed_email_template');
    }

    /**
     * @return mixed
     */
    public function getFailedEmailCopy()
    {

        return $this->getData('failed_email_copy');
    }

    /**
     * @return mixed
     */
    public function getFailedEmailCopyMethod()
    {

        return $this->getData('failed_email_copy_method');
    }

    /**
     * @param $lastExecuted
     *
     * @return $this
     */
    public function setLastExecuted($lastExecuted)
    {

        return $this->setData('last_executed', $lastExecuted);
    }

    /**
     * @return mixed
     */
    public function getLastExecuted()
    {

        return $this->getData('last_executed');
    }

    /**
     * @param $cron
     *
     * @return $this
     */
    public function setCron($cron)
    {

        return $this->setData('cron', $cron);
    }

    /**
     * @return mixed
     */
    public function getCron()
    {

        return $this->getData('cron');
    }

    /**
     * @param $afterImport
     *
     * @return $this
     */
    public function setAfterImport($afterImport)
    {

        return $this->setData('after_import', $afterImport);
    }

    /**
     * @return mixed
     */
    public function getAfterImport()
    {

        return $this->getData('after_import');
    }
}
