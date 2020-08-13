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

namespace Licentia\Import\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;

/**
 * Class Save
 *
 * @package Licentia\Import\Controller\Adminhtml\Import
 */
class Save extends \Licentia\Import\Controller\Adminhtml\Import
{

    /**
     * @return \Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {

        parent::execute();
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data = $this->getRequest()->getParams()) {
            $id = $this->getRequest()->getParam('id');

            /** @var \Licentia\Import\Model\Import $model */
            $model = $this->registry->registry('panda_import');

            $finalMappings = [];
            if (isset($data['mappings']['magento'])) {
                for ($i = 0; $i < count($data['mappings']['magento']); $i++) {

                    if (!empty($data['mappings']['magento'][$i]) || !empty($data['mappings']['remote'][$i])) {
                        $finalMappings['magento'][] = $data['mappings']['magento'][$i];
                        $finalMappings['remote'][] = $data['mappings']['remote'][$i];
                        $finalMappings['default'][] = $data['mappings']['default'][$i];
                    }
                }
            }

            $data['mappings'] = json_encode($finalMappings);

            if (!$model->getId() && $id) {
                $this->messageManager->addErrorMessage(__('This Scheduled Import no longer exists.'));

                return $resultRedirect->setPath('*/*/');
            }

            try {

                if (isset($data['ftp_password']) &&
                    $data['ftp_password'] == \Licentia\Panda\Model\Senders::OBSCURE_PASSWORD_REPLACEMENT) {
                    unset($data['ftp_password']);
                }
                if (isset($data['remote_password']) &&
                    $data['remote_password'] == \Licentia\Panda\Model\Senders::OBSCURE_PASSWORD_REPLACEMENT) {
                    unset($data['remote_password']);
                }

                if (isset($data['remote_bearer']) &&
                    $data['remote_bearer'] == \Licentia\Panda\Model\Senders::OBSCURE_PASSWORD_REPLACEMENT) {
                    unset($data['remote_bearer']);
                }

                $model->addData($data);
                $model->save();

                $this->messageManager->addSuccessMessage(__('You saved the Scheduled Import.'));
                $this->_getSession()->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath(
                        '*/*/edit',
                        [
                            'id'     => $model->getId(),
                            'tab_id' => $this->getRequest()->getParam('active_tab'),
                        ]
                    );
                }

                return $resultRedirect->setPath('*/*/');
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\RuntimeException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e,
                    __('Something went wrong while saving the Scheduled Import. '));
            }

            $this->_getSession()->setFormData($data);

            return $resultRedirect->setPath(
                '*/*/edit',
                [
                    'id'     => $model->getId(),
                    'tab_id' => $this->getRequest()->getParam('active_tab'),
                ]
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
