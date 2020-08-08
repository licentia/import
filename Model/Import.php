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
use Magento\ImportExport\Model\Import\Adapter;
use phpseclib\Net\SFTP;

/**
 * Class Import
 *
 * @package Licentia\Import\Model
 */
class Import extends \Magento\Framework\Model\AbstractModel
{

    /**
     *
     */
    const XML_PATH_PANDA_IMPORT_TEMPLATE = 'panda/import/failure/template';

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
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $inlineTranslation;

    /**
     * Import constructor.
     *
     * @param \Magento\Framework\Translate\Inline\StateInterface           $inlineTranslation
     * @param \Magento\Store\Model\StoreManagerInterface                   $storeManager
     * @param \Magento\Framework\Mail\Template\TransportBuilder            $transportBuilder
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $scopeConfig
     * @param MagentoImport                                                $importModel
     * @param \Magento\Framework\Filesystem                                $filesystem
     * @param \Licentia\Panda\Helper\Data                                  $pandaHelper
     * @param \Magento\Framework\Model\Context                             $context
     * @param \Magento\Framework\Registry                                  $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null           $resourceCollection
     * @param array                                                        $data
     * @param MagentoImport\ImageDirectoryBaseProvider|null                $imageDirectoryBaseProvider
     */
    public function __construct(
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
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

        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->importModel = $importModel;
        $this->filesystem = $filesystem;
        $this->pandaHelper = $pandaHelper;
        $this->inlineTranslation = $inlineTranslation;
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

        if ($this->getFtpPassword()) {
            $this->setFtpPassword($this->pandaHelper->getEncryptor()->decrypt($this->getFtpPassword()));
        }

        return parent::_afterLoad();
    }

    /**
     *
     */
    public function beforeSave()
    {

        if ($this->getFtpPassword()) {
            $this->setFtpPassword($this->pandaHelper->getEncryptor()->encrypt($this->getFtpPassword()));
        }

        if ($this->getCron() !== 'other') {
            $this->setCronExpression($this->getCron());
        }

        $cron = \Cron\CronExpression::factory($this->getCronExpression());
        $currentTime = $this->pandaHelper->gmtDateTime();
        $nextExecutionAfter = $cron->getNextRunDate($currentTime)->format('Y-m-d H:i:s');
        $this->setNextExecution($nextExecutionAfter);

        return parent::beforeSave();
    }

    /**
     * @return Import
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validateBeforeSave()
    {

        $expr = $this->getCron();

        if ($this->getCron() == 'other') {
            $expr = $this->getCronExpression();
        }

        if (!\Cron\CronExpression::isValidExpression($expr)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid Cron expression'));
        }

        return parent::validateBeforeSave();
    }

    /**
     *
     */
    public function executeCron()
    {

        $collection = $this->getCollection()
                           ->addFieldToFilter('is_active', 1);

        /** @var Import $job */
        foreach ($collection as $job) {
            $cron = \Cron\CronExpression::factory($job->getCronExpression());
            $currentTime = $this->pandaHelper->gmtDateTime();
            $nextRun = $cron->getNextRunDate($job->getLastExecuted())->format('Y-m-d H:i:s');
            $nextExecutionAfter = $cron->getNextRunDate()->format('Y-m-d H:i:s');
            if ($currentTime >= $nextRun) {
                $job->load($job->getId())->setNextExecution($nextExecutionAfter)->run();
            }
        }
    }

    /**
     * @param $message
     */
    public function sendErrorEmail($message)
    {

        try {
            $emails = explode(',', $this->getFailedEmailRecipient());
            foreach ($emails as $key => $value) {
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    unset($emails[$key]);
                }
            }
            if ($emails) {
                $emails = array_unique($emails);
                $emails = array_values($emails);
            }

            $recipients = $emails;
            if ($this->getFailedEmailCopyMethod() == 'bcc') {
                $recipients = [$emails[0]];
                unset($emails[0]);
            }

            foreach ($recipients as $email) {
                $transport = $this->transportBuilder
                    ->setTemplateIdentifier('panda_import_failure_template',)->setTemplateOptions(
                        [
                            'area'  => \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE,
                            'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                        ]
                    )
                    ->setTemplateVars([
                        'message' => $message,
                        'name'    => $this->getName(),
                    ])
                    ->setFromByScope($this->getFailedEmailSender())
                    ->addTo($email)
                    ->getTransport();

                if ($this->getFailedEmailCopyMethod() == 'bcc') {
                    foreach ($emails as $copy) {
                        $transport->getMessage()->addBcc($copy);
                    }
                }

                $transport->sendMessage();
            }

        } catch (\Exception $e) {
            $this->pandaHelper->logException($e);
        }
    }

    /**
     * @return false|string|null
     */
    public function getFullFilePathName()
    {

        $localFile = null;

        if ($this->getServerType() == 'local') {
            $fileDir = $this->getFileDirectory();
            $fileName = $fileDir . $this->getFileName();

            $exists = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)
                                       ->isFile($fileName);

            if (!$exists) {
                return false;
            }

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
            $connId = ftp_connect($this->getFtpHost(), $this->getFtpPort() ?? 21);
            if (!@ftp_login($connId, $this->getFtpUsername(), $this->getFtpPassword())) {
                $this->importModel->getErrorAggregator()->addError(
                    \Magento\ImportExport\Model\Import\Entity\AbstractEntity::ERROR_CODE_SYSTEM_EXCEPTION,
                    \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError::ERROR_LEVEL_CRITICAL,
                    null,
                    null,
                    null,
                    "Can't connect to FTP Server: " . $this->getFtpHost()
                );
            }

            if ($this->getFtpPassiveMode()) {
                ftp_pasv($connId, true);
            }

            $file = null;
            try {
                $file = ftp_get($connId, $localFile, $fileName, $binary);
            } catch (\Exception $e) {
                return false;
            }

            if (!$file) {
                return false;
            }

            if ($this->getAfterImport() == 'archive') {

                $currentDir = ftp_pwd($connId);
                if (!ftp_chdir($connId, $archiveDir)) {
                    ftp_mkdir($connId, $archiveDir);
                }
                ftp_chdir($connId, $currentDir);
                if (!@ftp_chdir($connId, $this->getImportImagesFileDir() . 'archives/')) {
                    ftp_mkdir($connId, $this->getImportImagesFileDir() . 'archives/');
                }
                ftp_chdir($connId, $currentDir);
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
                        $this->getImportImagesFileDir() . 'archives/' . date('Y-m-d_H-i') . '_' . $image['name']);
                }
                if ($this->getAfterImport() == 'delete') {
                    ftp_delete($connId, $this->getImportImagesFileDir() . $image['name']);
                }
            }

            if ($this->getAfterImport() == 'archive') {
                ftp_rename($connId, $fileName, $archiveDir . date('Y-m-d_H-i') . '_' . $this->getFileName());
            }

            if ($this->getAfterImport() == 'delete') {
                ftp_delete($connId, $fileName);
            }

            ftp_close($connId);
        }

        if ($this->getServerType() == 'ssh') {

            $fileDir = $this->getFileDirectory();
            $fileName = $fileDir . $this->getFileName();
            $archiveDir = $fileDir . 'archives/';

            $localFile = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)
                                          ->getAbsolutePath('importexport/' . $this->getEntityType() . '.csv');

            $mediaDir = $this->imagesDirProvider->getDirectory()->getAbsolutePath();

            $sftp = new SFTP($this->getFtpHost(), $this->getFtpPort() ?? 22);
            if (!$sftp->login($this->getFtpUsername(), $this->getFtpPassword())) {
                $this->importModel->getErrorAggregator()->addError(
                    \Magento\ImportExport\Model\Import\Entity\AbstractEntity::ERROR_CODE_SYSTEM_EXCEPTION,
                    \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError::ERROR_LEVEL_CRITICAL,
                    null,
                    null,
                    null,
                    "Can't connect to SERVER: " . $this->getFtpHost()
                );
            }

            if (!$sftp->get($fileName, $localFile)) {
                return false;
            }

            if ($this->getAfterImport() == 'archive') {

                if (!$sftp->is_dir($archiveDir)) {
                    $sftp->mkdir($archiveDir);
                }
                if (!$sftp->is_dir($this->getImportImagesFileDir() . 'archives/')) {
                    $sftp->mkdir($this->getImportImagesFileDir() . 'archives/');
                }

            }

            $images = $sftp->rawlist($this->getImportImagesFileDir());

            foreach ($images as $image) {

                if ($image['type'] != 1) {
                    continue;
                }

                $type = pathinfo($image['filename'], PATHINFO_EXTENSION);

                if (!in_array(strtolower($type), ['png', 'jpg', 'jpeg'])) {
                    continue;
                }

                $sftp->get($this->getImportImagesFileDir() . $image['filename'], $mediaDir . $image['filename']);

                if ($this->getAfterImport() == 'archive') {
                    $sftp->rename($this->getImportImagesFileDir() . $image['filename'],
                        $this->getImportImagesFileDir() . 'archives/' . date('Y-m-d_H-i') . '_' . $image['filename']);
                }
                if ($this->getAfterImport() == 'delete') {
                    $sftp->delete($this->getImportImagesFileDir() . $image['filename'], false);
                }
            }

            if ($this->getAfterImport() == 'archive') {
                $sftp->rename($fileName, $archiveDir . date('Y-m-d_H-i') . '_' . $this->getFileName());
            }

            if ($this->getAfterImport() == 'delete') {
                $sftp->delete($sftp, $fileName);
            }
        }

        return $localFile;
    }

    /**
     *
     */
    public function run()
    {

        $result = 'success';
        $fullFileNamePath = null;
        try {

            $fullFileNamePath = $this->getFullFilePathName();

            if ($fullFileNamePath) {
                $data = $this->getData();
                $data['behavior'] = $this->getImportBehavior();
                $data['entity'] = $this->getEntityType();
                $data['validation_strategy'] = $this->getOnError();
                $data['_import_field_separator'] = $this->getImportFieldSeparator();
                $data['_import_multiple_value_separator'] = $this->getImportMultipleValueSeparator();
                $data['_import_empty_attribute_value_constant'] = $this->getImportEmptyAttributeValueConstant();

                if ($this->getServerType() == 'local') {
                    $data['import_images_file_dir'] = $this->getImportImagesFileDir();
                } else {
                    $data['import_images_file_dir'] = '';
                }
                $data['import_file'] = $fullFileNamePath;

                $this->importModel->setData($data);
                $this->importModel->setData('images_base_directory', $this->imagesDirProvider->getDirectory());
                $errorAggregator = $this->importModel->getErrorAggregator();
                $errorAggregator->initValidationStrategy(
                    $this->importModel->getData(MagentoImport::FIELD_NAME_VALIDATION_STRATEGY),
                    $this->importModel->getData(MagentoImport::FIELD_NAME_ALLOWED_ERROR_COUNT)
                );

                $this->importModel->validateSource($this->getSource($fullFileNamePath));

                $this->importModel->importSource();
            } else {
                $result = 'success_no_file';
            }

        } catch (\Exception $e) {

            $message = implode("<br><br>", $this->importModel->getErrorAggregator()->getAllErrors());

            if (!$message) {
                $message = $e->getMessage();
            }
            $result = 'fail';
            $this->sendErrorEmail($message);
        }

        if ($fullFileNamePath) {
            if (!$this->importModel->getErrorAggregator()->hasToBeTerminated()) {
                $this->importModel->invalidateIndex();
            }
        }

        $this->setLastExecuted($this->pandaHelper->gmtDateTime());
        $this->setLastExecutionStatus($result);
        $this->save();

        if ($result == 'success') {

            if ($this->getServerType() == 'local') {
                $fileDir = $this->getFileDirectory();
                $mediaDir = $this->imagesDirProvider->getDirectory()
                                                    ->getAbsolutePath($this->getImportImagesFileDir());
            } else {
                $fileDir = $localFile = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)
                                                         ->getAbsolutePath('importexport/');
                $mediaDir = $this->imagesDirProvider->getDirectory()
                                                    ->getAbsolutePath();
            }

            $fileName = $fileDir . $this->getFileName();

            $archivesDirExists = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)
                                                  ->isDirectory($mediaDir . 'archives');
            if (!$archivesDirExists) {
                $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                 ->create($mediaDir . 'archives');
            }
            $archiveMediaDir = $mediaDir . 'archives/';

            foreach (new \DirectoryIterator($mediaDir) as $file) {
                if ($file->isFile()) {
                    if (!in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg'])) {
                        continue;
                    }
                    if ($this->getAfterImport() == 'archive') {

                        $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                         ->renameFile($file->getRealPath(),
                                             $archiveMediaDir . 'panda_' . date('Y-m-d_H-i') . '_' . $file->getFileName());
                    }
                    if ($this->getAfterImport() == 'delete') {
                        $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                         ->delete($file->getRealPath());
                    }
                }
            }

            if ($this->getServerType() == 'local') {
                $localFile = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)
                                              ->getAbsolutePath($fileName);
            } else {
                $localFile = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)
                                              ->getAbsolutePath('importexport/' . $this->getEntityType() . '.csv');
            }

            if ($this->getAfterImport() == 'archive') {

                $archivesDirExists = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)
                                                      ->isDirectory('var/import_history');
                if (!$archivesDirExists) {
                    $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                     ->create('var/import_history');
                }
                $archiveDir = 'var/import_history/';

                $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                 ->renameFile($localFile,
                                     $archiveDir . 'panda_' . date('Y-m-d_H-i') . '_' . $this->getFileName());
            }

            if ($this->getAfterImport() == 'delete') {
                $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                 ->delete($localFile);
            }

        }

    }

    /**
     * @param $sourceFile
     *
     * @return MagentoImport\AbstractSource
     */
    public function getSource($sourceFile)
    {

        return Adapter::findAdapterFor(
            $sourceFile,
            $this->filesystem->getDirectoryWrite(DirectoryList::ROOT),
            $this->getData(MagentoImport::FIELD_FIELD_SEPARATOR)
        );
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

        if (!$this->getData('import_images_file_dir')) {
            return '';
        }

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

    /**
     * @param $failedEmailRecipient
     *
     * @return $this
     */
    public function setFailedEmailRecipient($failedEmailRecipient)
    {

        return $this->setData('failed_email_recipient', $failedEmailRecipient);
    }

    /**
     * @return mixed
     */
    public function getFailedEmailRecipient()
    {

        return $this->getData('failed_email_recipient');
    }

    /**
     * @param $ftpPort
     *
     * @return $this
     */
    public function setFtpPort($ftpPort)
    {

        return $this->setData('ftp_port', $ftpPort);
    }

    /**
     * @return mixed
     */
    public function getFtpPort()
    {

        return $this->getData('ftp_port');
    }

    /**
     * @param $cronExpression
     *
     * @return $this
     */
    public function setCronExpression($cronExpression)
    {

        return $this->setData('cron_expression', $cronExpression);
    }

    /**
     * @return mixed
     */
    public function getCronExpression()
    {

        return $this->getData('cron_expression');
    }

    /**
     * @param $lastExecutionStatus
     *
     * @return $this
     */
    public function setLastExecutionStatus($lastExecutionStatus)
    {

        return $this->setData('last_execution_status', $lastExecutionStatus);
    }

    /**
     * @return mixed
     */
    public function getLastExecutionStatus()
    {

        return $this->getData('last_execution_status');
    }

    /**
     * @param $nextExecution
     *
     * @return $this
     */
    public function setNextExecution($nextExecution)
    {

        return $this->setData('next_execution', $nextExecution);
    }

    /**
     * @return mixed
     */
    public function getNextExecution()
    {

        return $this->getData('next_execution');
    }

}
