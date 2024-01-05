<?php

namespace Clerk\Clerk\Controller\Adminhtml\Dashboard;

use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\View\Result\PageFactory;

class Email extends Action
{
    /**
     * @var
     */
    protected ClerkLogger $clerkLogger;

    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ClerkLogger $clerkLogger
     */
    public function __construct(
        Context     $context,
        PageFactory $resultPageFactory,
        ClerkLogger $clerkLogger
    )
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->clerkLogger = $clerkLogger;
    }

    /**
     * @return Page|null
     * @throws FileSystemException
     */
    public function execute(): ?Page
    {
        try {

            /** @var Page $resultPage */
            $resultPage = $this->resultPageFactory->create();
            $resultPage->setActiveMenu('Clerk_Clerk::report_clerkroot_email_insights');
            $resultPage->addBreadcrumb(__('Clerk.io - Email Insights'), __('Clerk.io - Email Insights'));
            $resultPage->getConfig()->getTitle()->prepend(__('Clerk.io - Email Insights'));

            return $resultPage;

        } catch (Exception $e) {

            $this->clerkLogger->error('Email execute ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }
}
