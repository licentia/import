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
 * Class Run
 *
 * @package Licentia\Import\Controller\Adminhtml\Import
 */
class Run extends \Licentia\Import\Controller\Adminhtml\Import
{

    /**
     * Delete action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {

        parent::execute();
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        /** @var \Licentia\Import\Model\Import $model */
        $model = $this->registry->registry('panda_import');

        if ($model->getId()) {
            try {
                $model->run();
                $this->messageManager->addSuccessMessage(__('Job Successfully Run'));
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\RuntimeException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while running the Scheduled Import')
                );
            }

            return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
        }
        $this->messageManager->addErrorMessage(__('We can\'t find an Scheduled Import to run.'));

        return $resultRedirect->setPath('*/*/');
    }
}
