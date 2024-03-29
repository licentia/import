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

/**
 * Class Edit
 *
 * @package Licentia\Import\Controller\Adminhtml\Import
 */
class Edit extends \Licentia\Import\Controller\Adminhtml\Import
{

    /**
     * Init actions
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    protected function _initAction()
    {

        // load layout, set active menu and breadcrumbs
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Licentia_Import::import')
                   ->addBreadcrumb(__('Sales Automation'), __('Sales Automation'))
                   ->addBreadcrumb(__('Manage Scheduled Import'), __('Manage Scheduled Import'));

        return $resultPage;
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Page|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {

        parent::execute();
        $id = $this->getRequest()->getParam('id');

        /** @var \Licentia\Import\Model\Import $model */
        $model = $this->registry->registry('panda_import');

        if ($id) {
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This Scheduled Import no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        $data = $this->_getSession()->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        $model->setFtpPassword(\Licentia\Import\Model\Import::OBSCURE_PASSWORD_REPLACEMENT);

        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb($id ? __('Edit Scheduled Import') : __('New Scheduled Import'),
            $id ? __('Edit Scheduled Import') : __('New Scheduled Import'));
        $resultPage->getConfig()
                   ->getTitle()->prepend(__('Scheduled Import'));
        $resultPage->getConfig()
                   ->getTitle()->prepend($model->getId() ? $model->getName() : __('New Scheduled Import'));

        $resultPage->addContent(
            $resultPage->getLayout()
                       ->createBlock('Licentia\Import\Block\Adminhtml\Import\Edit')
        )
                   ->addLeft(
                       $resultPage->getLayout()
                                  ->createBlock('Licentia\Import\Block\Adminhtml\Import\Edit\Tabs')
                   );

        return $resultPage;
    }
}
