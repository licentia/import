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
use \Magento\ImportExport\Model\Import\ImageDirectoryBaseProvider;

/**
 * Class Import
 *
 * @package Licentia\Import\Model
 */
class Import extends \Magento\Framework\Model\AbstractModel
{

    const OBSCURE_PASSWORD_REPLACEMENT = 'nothingtoseehere';

    /**
     *
     */
    const LOCAL_IMPORT_PATH = 'var/importexport/';

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
    protected $importHelper;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var MagentoImport
     */
    protected $importModel;

    /**
     * @var ImageDirectoryBaseProvider|mixed
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
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curl;

    /**
     * @var LogFactory
     */
    protected $logFactory;

    /**
     * Import constructor.
     *
     * @param LogFactory                                                   $logFactory
     * @param \Magento\Framework\HTTP\Client\Curl                          $curl
     * @param \Magento\Framework\Translate\Inline\StateInterface           $inlineTranslation
     * @param \Magento\Store\Model\StoreManagerInterface                   $storeManager
     * @param \Magento\Framework\Mail\Template\TransportBuilder            $transportBuilder
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $scopeConfig
     * @param MagentoImport                                                $importModel
     * @param \Magento\Framework\Filesystem                                $filesystem
     * @param \Licentia\Import\Helper\Data                                 $importHelper
     * @param \Magento\Framework\Model\Context                             $context
     * @param \Magento\Framework\Registry                                  $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null           $resourceCollection
     * @param array                                                        $data
     * @param ImageDirectoryBaseProvider|null                              $imageDirectoryBaseProvider
     */
    public function __construct(
        LogFactory $logFactory,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        MagentoImport $importModel,
        \Magento\Framework\Filesystem $filesystem,
        \Licentia\Import\Helper\Data $importHelper,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        ?ImageDirectoryBaseProvider $imageDirectoryBaseProvider = null
    ) {

        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->curl = $curl;
        $this->logFactory = $logFactory;
        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->importModel = $importModel;
        $this->filesystem = $filesystem;
        $this->importHelper = $importHelper;
        $this->inlineTranslation = $inlineTranslation;
        $this->imagesDirProvider = $imageDirectoryBaseProvider
                                   ?? ObjectManager::getInstance()->get(ImageDirectoryBaseProvider::class);
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
            $this->setFtpPassword($this->importHelper->getEncryptor()->decrypt($this->getFtpPassword()));
        }
        if ($this->getRemotePassword()) {
            $this->setRemotePassword($this->importHelper->getEncryptor()->decrypt($this->getRemotePassword()));
        }
        if ($this->getRemoteBearer()) {
            $this->setRemoteBearer($this->importHelper->getEncryptor()->decrypt($this->getRemoteBearer()));
        }

        return parent::_afterLoad();
    }

    /**
     *
     */
    public function beforeSave()
    {

        if ($this->getFtpPassword()) {
            $this->setFtpPassword($this->importHelper->getEncryptor()->encrypt($this->getFtpPassword()));
        }

        if ($this->getRemotePassword()) {
            $this->setRemotePassword($this->importHelper->getEncryptor()->encrypt($this->getRemotePassword()));
        }

        if ($this->getRemoteBearer()) {
            $this->setRemoteBearer($this->importHelper->getEncryptor()->encrypt($this->getRemoteBearer()));
        }

        if ($this->getCron() !== 'other') {
            $this->setCronExpression($this->getCron());
        }

        $cron = \Cron\CronExpression::factory($this->getCronExpression());
        $currentTime = $this->importHelper->gmtDateTime();
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

        if ($this->getFileName() && $this->getServerType() != 'url') {
            $extension = $this->getFileExtension($this->getFileName());

            if (!in_array($extension, ['csv', 'xml', 'zip', 'json'])) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Invalid File Extension'));
            }

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
            $currentTime = $this->importHelper->gmtDateTime();
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
                $t = $this->transportBuilder->setTemplateIdentifier('panda_import_failure_template')
                                            ->setTemplateOptions(
                                                [
                                                    'area'  => \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE,
                                                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                                                ]
                                            )
                                            ->setTemplateVars(
                                                [
                                                    'message' => $message,
                                                    'name'    => $this->getName(),
                                                ]
                                            )
                                            ->setFromByScope($this->getFailedEmailSender())
                                            ->addTo($email)
                                            ->getTransport();

                if ($this->getFailedEmailCopyMethod() == 'bcc') {
                    foreach ($emails as $copy) {
                        $t->getMessage()->addBcc($copy);
                    }
                }

                $t->sendMessage();
            }

        } catch (\Exception $e) {
            $this->importHelper->logException($e);
        }
    }

    /**
     * @param $result
     */
    public function sendSuccessEmail($result)
    {

        try {
            $emails = explode(',', $this->getSuccessEmailRecipient());
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
            if ($this->getSuccessEmailCopyMethod() == 'bcc') {
                $recipients = [$emails[0]];
                unset($emails[0]);
            }

            if ($result == 'success') {
                $template = 'panda_import_success_template';
                $vars = [
                    'created' => $this->importModel->getCreatedItemsCount(),
                    'updated' => $this->importModel->getUpdatedItemsCount(),
                    'deleted' => $this->importModel->getDeletedItemsCount(),
                    'name'    => $this->getName(),
                ];
            } else {
                $template = 'panda_import_no_file_template';
                $vars = [
                    'name' => $this->getName(),
                ];
            }

            foreach ($recipients as $email) {
                $t = $this->transportBuilder->setTemplateIdentifier($template)
                                            ->setTemplateOptions(
                                                [
                                                    'area'  => \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE,
                                                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                                                ]
                                            )
                                            ->setTemplateVars($vars)
                                            ->setFromByScope($this->getSuccessEmailSender())
                                            ->addTo($email)
                                            ->getTransport();

                if ($this->getSuccessEmailCopyMethod() == 'bcc') {
                    foreach ($emails as $copy) {
                        $t->getMessage()->addBcc($copy);
                    }
                }

                $t->sendMessage();
            }

        } catch (\Exception $e) {
            $this->importHelper->logException($e);
        }
    }

    /**
     * @param      $path
     *
     * @return string|string[]
     */
    public function getFileExtension($path)
    {

        return pathinfo($path, PATHINFO_EXTENSION);

    }

    /**
     * @param $array
     *
     * @return int|mixed
     */
    public function countDimensions($array)
    {

        if (is_array(reset($array))) {
            $return = $this->countDimensions(reset($array)) + 1;
        } else {
            $return = 1;
        }

        return $return;
    }

    /**
     * @param $localFile
     * @param $extension
     *
     * @return string
     */
    public function convertToCsv($localFile, $extension)
    {

        $dirWrite = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT);
        $dirRead = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
        $fileInfo = pathinfo($localFile);

        $finalFile = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.csv';

        $data = [];

        if ($extension == 'zip') {
            $zip = new \ZipArchive;
            $res = $zip->open('file.zip');
            if ($res === true) {
                $dirName = $fileInfo['dirname'] . '/tmpZip_panda_' . rand(0, 1000);
                if (!$dirWrite->isDirectory()) {
                    $dirWrite->create($dirName);
                }
                $zip->extractTo($dirName);
                $zip->close();

                $files = array_diff(scandir($dirName), ['.', '..']);

                foreach ($files as $file) {
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    if (in_array($ext, ['csv', 'xml', 'json'])) {
                        $localFile = $dirRead->getAbsolutePath($dirName . '');
                    }
                }

                $dirWrite->delete($dirName);

                if ($localFile) {
                    return $this->convertToCsv($localFile, $ext);
                }

                return false;
            } else {
                return false;
            }
        }

        if ($extension == 'json') {
            $data = json_decode($dirWrite->readFile($localFile), true);
            if ($this->countDimensions($data) == 3) {
                $data = reset($data);
            }
        }

        if ($extension == 'xml') {
            $file = simplexml_load_file($localFile);
            $file = json_decode(json_encode($file), true);
            $data = reset($file);
        }

        if ($data) {

            $fp = fopen($finalFile, 'w');

            $fieldSeparator = $this->getFieldSeparator();

            fputcsv($fp, array_keys($data[0]), $fieldSeparator);

            foreach ($data as $line) {
                fputcsv($fp, $line, $fieldSeparator);
            }

            fclose($fp);

            $dirWrite->delete($localFile);

            return $finalFile;
        }

        return $localFile;
    }

    /**
     * @return false|string|null
     */
    public function getFullFilePathName()
    {

        $localFile = null;

        $dirWrite = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT);
        $dirRead = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);

        if ($this->getServerType() == 'local') {

            $fileDir = $this->getFileDirectory();
            $localFile = $dirRead->getAbsolutePath($fileName);

            $exists = $dirRead->isFile($localFile);

            if (!is_file($localFile)) {
                return false;
            }

            $fileExtension = $this->getFileExtension($fileName);

            $localFile = $dirRead->getAbsolutePath($fileName);

            if ($fileExtension != 'csv') {
                $localFile = $this->convertToCsv($localFile, $fileExtension);
            }
            $type = pathinfo($localFile);

            if ($type['basename'] != $this->getEntityType() . '.csv') {
                $newFile = $dirRead->getAbsolutePath($fileDir . $this->getEntityType() . '.csv');
                $dirWrite->renameFile($localFile, $newFile);
                $localFile = $newFile;
            }

        }

        if ($this->getServerType() == 'url') {

            if ($this->getRemoteUsername()) {
                $this->curl->setCredentials($this->getRemoteUsername(), $this->getRemotePassword());
            }

            if ($this->getRemoteBearer()) {
                $this->curl->addHeader('Authorization: Bearer', $this->getRemoteBearer());
            }

            $this->curl->get($this->getRemoteUrl());

            if (!$this->curl->getBody()) {
                return false;
            }

            $fileExtension = $this->getFileExtension($this->getRemoteUrl());
            $tmpFileName = $dirRead->getAbsolutePath(
                self::LOCAL_IMPORT_PATH . $this->getEntityType() . '.' .
                $fileExtension
            );

            $dirWrite->writeFile($tmpFileName, $this->curl->getBody());

            if ($fileExtension != 'csv') {
                $tmpFileName = $this->convertToCsv($tmpFileName, $fileExtension);
            }

            $localFile = $dirRead->getAbsolutePath($tmpFileName);

            $this->setFileName($this->getEntityType() . '.csv');

        }

        if ($this->getServerType() == 'ftp') {

            $fileDir = $this->getFileDirectory();
            $fileName = $fileDir . $this->getFileName();
            $archiveDir = $fileDir . 'archives/';

            $localFile = $dirWrite->getAbsolutePath(
                self::LOCAL_IMPORT_PATH . $this->getEntityType() . '.' .
                $this->getFileExtension($fileName)
            );
            $dirWrite->writeFile($localFile, '');

            $mediaDir = $this->imagesDirProvider->getDirectory()->getAbsolutePath();

            $binary = $this->getFtpFileMode() == 'binary' ? FTP_BINARY : FTP_ASCII;
            $connId = ftp_connect($this->getFtpHost(), $this->getFtpPort() ?? 21);
            if (!ftp_login($connId, $this->getFtpUsername(), $this->getFtpPassword())) {
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
                $extension = $this->getFileExtension($fileName);

                $this->_eventManager->dispatch(
                    'panda_import_connect_ftp',
                    [
                        'remote_file' => $fileName,
                        'local_file'  => $localFile,
                        'connection'  => $connId,
                        'adapter'     => $this->importModel,
                        'model'       => $this,
                    ]
                );

                if ($extension != 'csv') {
                    $localFile = $this->convertToCsv($localFile, $extension);
                }

            } catch (\Exception $e) {
                return false;
            }

            if (!$file) {
                return false;
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

                ftp_get(
                    $connId,
                    $mediaDir . $image['name'],
                    $this->getImportImagesFileDir() . $image['name'],
                    $binary
                );

            }

            ftp_close($connId);
        }

        if ($this->getServerType() == 'ssh') {

            $fileDir = $this->getFileDirectory();
            $fileName = $fileDir . $this->getFileName();

            $localFile = $dirWrite->getAbsolutePath(
                self::LOCAL_IMPORT_PATH . $this->getEntityType() . '.' .
                $this->getFileExtension($fileName)
            );

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

            $this->_eventManager->dispatch(
                'panda_import_connect_sftp',
                [
                    'remote_file' => $fileName,
                    'local_file'  => $localFile,
                    'connection'  => $sftp,
                    'adapter'     => $this->importModel,
                    'model'       => $this,
                ]
            );

            $extension = $this->getFileExtension($fileName);
            if ($extension != 'csv') {
                $localFile = $this->convertToCsv($localFile, $extension);
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
            }

        }

        return $localFile;
    }

    /**
     * @return array
     */
    public function getMappingsArray()
    {

        $mappings = json_decode($this->getMappings(), true);
        $finalMappings = [];
        if (isset($mappings['magento'])) {
            for ($i = 0; $i < count($mappings['magento']); $i++) {
                if ((!empty($mappings['magento'][$i]) && !empty($mappings['remote'][$i])) ||
                    !empty($mappings['magento'][$i]) && !empty($mappings['default'][$i])) {
                    $finalMappings[] = [
                        'magento' => $mappings['magento'][$i],
                        'remote'  => $mappings['remote'][$i],
                        'default' => $mappings['default'][$i],
                    ];
                }
            }
        }

        return $finalMappings;
    }

    /**
     * @param $resultData
     * @param $mappings
     *
     * @return mixed
     */
    public function replaceExpression($resultData, $mappings)
    {

        foreach ($mappings as $key => $row) {

            $line = $row['default'];

            if (stripos($line, '{') !== false) {

                preg_match_all('/\{([a-z0-9_]+)\}/si', $line, $resultProduct);

                foreach ($resultProduct[1] as $a => $item) {

                    foreach ($resultData as $rIndex => $rValue) {

                        foreach ($rValue as $fKey => $fValue) {

                            if (stripos($fValue, '{') === false) {
                                continue;
                            }

                            foreach ($resultProduct[1] as $rR1) {
                                $resultData[$rIndex][$fKey] = str_replace(
                                    '{' . $rR1 . '}',
                                    $rValue[$rR1],
                                    $resultData[$rIndex][$fKey]
                                );

                                $resultData[$rIndex][$fKey] = str_replace(
                                    [
                                        '\+',
                                        '\-',
                                        '\/',
                                        '\*',
                                        '\(',
                                        '\)',
                                    ],
                                    [
                                        'PANDA_PLUS',
                                        'PANDA_MINUS',
                                        'PANDA_DIVIDE',
                                        'PANDA_MULTIPLY',
                                        'PANDA_OPEN_P',
                                        'PANDA_CLOSE_P',
                                    ],
                                    $resultData[$rIndex][$fKey]
                                );

                                $tmpResult = str_split($resultData[$rIndex][$fKey]);
                                try {
                                    if (array_intersect(['+', '-', '/', '*'], $tmpResult)) {
                                        $resultData[$rIndex][$fKey] = $this->importHelper->evaluateExpression(
                                            $resultData[$rIndex][$fKey]
                                        );
                                    }
                                } catch (\Exception $e) {

                                }

                                $resultData[$rIndex][$fKey] = str_replace(
                                    [
                                        'PANDA_PLUS',
                                        'PANDA_MINUS',
                                        'PANDA_DIVIDE',
                                        'PANDA_MULTIPLY',
                                        'PANDA_OPEN_P',
                                        'PANDA_CLOSE_P',
                                    ],
                                    [
                                        '+',
                                        '-',
                                        '/',
                                        '*',
                                        '(',
                                        ')',
                                    ],
                                    $resultData[$rIndex][$fKey]
                                );
                            }

                        }
                    }

                }
            }

        }

        return $resultData;

    }

    /**
     * @param $file
     *
     * @return false
     */
    public function applyDataMappings($file)
    {

        $mappings = (array) $this->getMappingsArray();
        $ignoreColumns = explode(',', $this->getIgnoreColumns());
        $ignoreColumns = array_map('trim', $ignoreColumns);
        $ignoreColumns = array_filter($ignoreColumns);

        if (!$mappings && !$ignoreColumns) {
            return false;
        }

        $fieldSeparator = $this->getFieldSeparator();
        $fieldEnclosure = $this->getFieldsEnclosure();

        $row = 1;
        $resultData = [];
        if (($handle = fopen($file, "r")) !== false) {
            while (($data = fgetcsv($handle, 0, $fieldSeparator)) !== false) {

                if ($row == 1) {
                    foreach ($mappings as $mapping) {

                        if (!empty($mapping['magento']) &&
                            !empty($mapping['remote']) &&
                            array_search($mapping['remote'], $data) !== false) {
                            $data[array_search($mapping['remote'], $data)] = $mapping['magento'];
                        }

                        if (!empty($mapping['magento']) &&
                            empty($mapping['remote']) &&
                            !empty($mapping['default'])) {
                            $data[] = $mapping['magento'];
                        }

                    }

                    $map = $data;
                    $row++;
                    continue;
                }

                foreach ($mappings as $key => $mapping) {

                    if (!empty($mapping['magento']) &&
                        empty($mapping['remote']) &&
                        !empty($mapping['default']) &&
                        array_search($mapping['remote'], $data) !== false) {
                        $data[array_search($mapping['remote'], $data)] = $mapping['default'];
                    }

                    if (!empty($mapping['magento']) &&
                        !empty($mapping['remote']) &&
                        array_search($mapping['remote'], $data) !== false) {
                        $data[array_search($mapping['remote'], $data)] = $mapping['magento'];
                    }

                    if (!empty($mapping['magento']) &&
                        empty($mapping['remote']) &&
                        !empty($mapping['default'])) {
                        $data[] = $mapping['default'];
                    }

                }

                $tmpResult = array_combine($map, $data);
                $resultData[] = array_diff_key($tmpResult, array_flip($ignoreColumns));

            }
        }

        if ($resultData) {

            $resultData = $this->replaceExpression($resultData, $mappings);

            $fp = fopen($file, 'w');
            fputcsv($fp, array_keys($resultData[0]), $fieldSeparator, $fieldEnclosure);

            foreach ($resultData as $fields) {
                fputcsv($fp, $fields, $fieldSeparator, $fieldEnclosure);
            }

            fclose($fp);
        }

        return $file;
    }

    /**
     *
     */
    public function run()
    {

        $result = 'success';
        $message = '';
        $fullFileNamePath = null;
        try {

            $fullFileNamePath = $this->getFullFilePathName();

            if ($fullFileNamePath) {
                $this->applyDataMappings($fullFileNamePath);
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

                $this->_eventManager->dispatch(
                    'panda_import_before_validate',
                    [
                        'file'    => $fullFileNamePath,
                        'adapter' => $this->importModel,
                        'model'   => $this,
                    ]
                );

                $this->importModel->validateSource($this->getSource($fullFileNamePath));

                $this->importModel->importSource();

                if ($this->importModel->getErrorAggregator()->getErrorsCount()) {

                    $message = [];
                    foreach ($this->importModel->getErrorAggregator()->getAllErrors() as $error) {
                        $message[] = $error->getErrorMessage() . ' - Row: ' . $error->getRowNumber();
                    }

                    $result = 'fail';
                    $message = implode("<br><br>", $message);
                    $this->sendErrorEmail($message);
                    $this->logFactory->create()
                                     ->setData(
                                         [
                                             'import_id' => $this->getId(),
                                             'result'    => $result,
                                             'message'   => $message,
                                         ]
                                     )
                                     ->save();

                }

            } else {
                $result = 'success_no_file';
            }

        } catch (\Exception $e) {
            $message = $e->getMessage();
            $result = 'fail';
            $this->sendErrorEmail($message);

            $this->logFactory->create()
                             ->setData(
                                 [
                                     'import_id' => $this->getId(),
                                     'result'    => $result,
                                     'message'   => $message,
                                 ]
                             )
                             ->save();
        }

        if ($fullFileNamePath && !$this->importModel->getErrorAggregator()->hasToBeTerminated()) {
            $this->importModel->invalidateIndex();
        }

        if ($result == 'success') {

            if ($this->getServerType() == 'local') {
                $fileDir = $this->getFileDirectory();
                $mediaDir = $this->imagesDirProvider->getDirectory()
                                                    ->getAbsolutePath($this->getImportImagesFileDir());
            } else {
                $fileDir = $localFile = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)
                                                         ->getAbsolutePath(self::LOCAL_IMPORT_PATH);
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
                                         ->renameFile(
                                             $file->getRealPath(),
                                             $archiveMediaDir . 'panda_' . date('Y-m-d_H-i') . '_' .
                                             $file->getFileName()
                                         );
                    }
                    if ($this->getAfterImport() == 'delete') {
                        $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                         ->delete($file->getRealPath());
                    }
                }
            }

            $fileInfo = pathinfo($fullFileNamePath);

            $localFile = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.csv';

            if ($this->getAfterImport() == 'archive') {

                $archivesDirExists = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)
                                                      ->isDirectory('var/import_history');
                if (!$archivesDirExists) {
                    $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                     ->create('var/import_history');
                }
                $archiveDir = 'var/import_history/';

                $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                 ->renameFile(
                                     $localFile,
                                     $archiveDir . 'panda_' . date('Y-m-d_H-i') . '_' . $fileInfo['filename'] . '.csv'
                                 );

            }

            if ($this->getAfterImport() == 'delete' && is_file($localFile)) {
                $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)
                                 ->delete($localFile);
            }

            $this->logFactory->create()
                             ->setData(
                                 [
                                     'import_id' => $this->getId(),
                                     'result'    => $result,
                                     'created'   => $this->importModel->getCreatedItemsCount(),
                                     'updated'   => $this->importModel->getUpdatedItemsCount(),
                                     'deleted'   => $this->importModel->getDeletedItemsCount(),
                                 ]
                             )
                             ->save();

            if ($this->getServerType() == 'ftp') {

                $fileDir = $this->getFileDirectory();
                $fileName = $fileDir . $this->getFileName();
                $archiveDir = $fileDir . 'archives/';
                $connId = ftp_connect($this->getFtpHost(), $this->getFtpPort() ?? 21);
                if (ftp_login($connId, $this->getFtpUsername(), $this->getFtpPassword())) {

                    if ($this->getFtpPassiveMode()) {
                        ftp_pasv($connId, true);
                    }

                    if ($this->getAfterImport() == 'archive') {
                        try {
                            $currentDir = ftp_pwd($connId);
                            if (!ftp_chdir($connId, $archiveDir)) {
                                ftp_mkdir($connId, $archiveDir);
                            }
                            ftp_chdir($connId, $currentDir);
                            if (!ftp_chdir($connId, $this->getImportImagesFileDir() . 'archives/')) {
                                ftp_mkdir($connId, $this->getImportImagesFileDir() . 'archives/');
                            }
                            ftp_chdir($connId, $currentDir);
                        } catch (\Exception $e) {
                        }
                    }

                    if ($this->getAfterImport() == 'archive') {
                        ftp_rename($connId, $fileName, $archiveDir . date('Y-m-d_H-i') . '_' . $this->getFileName());
                    }

                    if ($this->getAfterImport() == 'delete') {
                        ftp_delete($connId, $fileName);
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

                        if ($this->getAfterImport() == 'archive') {
                            ftp_rename(
                                $connId,
                                $this->getImportImagesFileDir() . $image['name'],
                                $this->getImportImagesFileDir() . 'archives/' . date('Y-m-d_H-i') . '_' . $image['name']
                            );
                        }
                        if ($this->getAfterImport() == 'delete') {
                            ftp_delete($connId, $this->getImportImagesFileDir() . $image['name']);
                        }
                    }

                    ftp_close($connId);
                }
            }

            if ($this->getServerType() == 'ssh') {

                $fileDir = $this->getFileDirectory();
                $fileName = $fileDir . $this->getFileName();
                $archiveDir = $fileDir . 'archives/';

                $sftp = new SFTP($this->getFtpHost(), $this->getFtpPort() ?? 22);
                if ($sftp->login($this->getFtpUsername(), $this->getFtpPassword())) {

                    if ($this->getAfterImport() == 'archive') {
                        if (!$sftp->is_dir($archiveDir)) {
                            $sftp->mkdir($archiveDir);
                        }
                        if (!$sftp->is_dir($this->getImportImagesFileDir() . 'archives/')) {
                            $sftp->mkdir($this->getImportImagesFileDir() . 'archives/');
                        }

                    }

                    if ($this->getAfterImport() == 'archive') {
                        $sftp->rename($fileName, $archiveDir . date('Y-m-d_H-i') . '_' . $this->getFileName());
                    }
                    if ($this->getAfterImport() == 'delete') {
                        $sftp->delete($fileName, false);
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

                        if ($this->getAfterImport() == 'archive') {
                            $sftp->rename(
                                $this->getImportImagesFileDir() . $image['filename'],
                                $this->getImportImagesFileDir() . 'archives/' . date(
                                    'Y-m-d_H-i'
                                ) . '_' . $image['filename']
                            );
                        }
                        if ($this->getAfterImport() == 'delete') {
                            $sftp->delete($this->getImportImagesFileDir() . $image['filename'], false);
                        }
                    }

                }

            }

        }

        $this->setLastExecuted($this->importHelper->gmtDateTime());
        $this->setLastExecutionStatus($result);
        $this->setFailMessage($message);
        $this->save();

        if ($result == 'success_no_file') {
            $this->logFactory->create()
                             ->setData(
                                 [
                                     'import_id' => $this->getId(),
                                     'result'    => $result,
                                     'created'   => 0,
                                     'updated'   => 0,
                                     'deleted'   => 0,
                                     'message'   => __('No File'),
                                 ]
                             )
                             ->save();
        }
        if ($result == 'success_no_file' || $result == 'success') {
            $this->sendSuccessEmail($result);

        }

        return $result;
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

        $fieldSeparator = $this->getData('field_separator');
        if (!$fieldSeparator) {
            $fieldSeparator = ',';
        }

        return $fieldSeparator;
    }

    /**
     * @return mixed
     */
    public function getFieldsEnclosure()
    {

        $fieldEnclosure = $this->getData('fields_enclosure');
        if (!$fieldEnclosure) {
            $fieldEnclosure = '"';
        }

        return $fieldEnclosure;
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

    /**
     * @param $remoteUsername
     *
     * @return $this
     */
    public function setRemoteUsername($remoteUsername)
    {

        return $this->setData('remote_username', $remoteUsername);
    }

    /**
     * @param $remotePassword
     *
     * @return $this
     */
    public function setRemotePassword($remotePassword)
    {

        return $this->setData('remote_password', $remotePassword);
    }

    /**
     * @param $remoteUrl
     *
     * @return $this
     */
    public function setRemoteUrl($remoteUrl)
    {

        return $this->setData('remote_url', $remoteUrl);
    }

    /**
     * @param $mappings
     *
     * @return $this
     */
    public function setMappings($mappings)
    {

        return $this->setData('mappings', $mappings);
    }

    /**
     * @return mixed
     */
    public function getRemoteUsername()
    {

        return $this->getData('remote_username');
    }

    /**
     * @return mixed
     */
    public function getRemotePassword()
    {

        return $this->getData('remote_password');
    }

    /**
     * @return mixed
     */
    public function getRemoteUrl()
    {

        return $this->getData('remote_url');
    }

    /**
     * @return mixed
     */
    public function getMappings()
    {

        return $this->getData('mappings');
    }

    /**
     * @param $bearer
     *
     * @return $this
     */
    public function setRemoteBearer($bearer)
    {

        return $this->setData('remote_bearer', $bearer);
    }

    /**
     * @return mixed
     */
    public function getRemoteBearer()
    {

        return $this->getData('remote_bearer');
    }

    /**
     * @param $successEmailCopyMethod
     *
     * @return $this
     */
    public function setSuccessEmailCopyMethod($successEmailCopyMethod)
    {

        return $this->setData('success_email_copy_method', $successEmailCopyMethod);
    }

    /**
     * @param $successEmailRecipient
     *
     * @return $this
     */
    public function setSuccessEmailRecipient($successEmailRecipient)
    {

        return $this->setData('success_email_recipient', $successEmailRecipient);
    }

    /**
     * @param $successEmailSender
     *
     * @return $this
     */
    public function setSuccessEmailSender($successEmailSender)
    {

        return $this->setData('success_email_sender', $successEmailSender);
    }

    /**
     * @return mixed
     */
    public function getSuccessEmailCopyMethod()
    {

        return $this->getData('success_email_copy_method');
    }

    /**
     * @return mixed
     */
    public function getSuccessEmailRecipient()
    {

        return $this->getData('success_email_recipient');
    }

    /**
     * @return mixed
     */
    public function getSuccessEmailSender()
    {

        return $this->getData('success_email_sender');
    }

    /**
     * @param $failMessage
     *
     * @return $this
     */
    public function setFailMessage($failMessage)
    {

        return $this->setData('fail_message', $failMessage);
    }

    /**
     * @return mixed
     */
    public function getFailMessage()
    {

        return $this->getData('fail_message');
    }

    /**
     * @param $ignoreColumns
     *
     * @return $this
     */
    public function setIgnoreColumns($ignoreColumns)
    {

        return $this->setData('ignore_columns', $ignoreColumns);
    }

    /**
     * @return mixed
     */
    public function getIgnoreColumns()
    {

        return $this->getData('ignore_columns');
    }

}
