<?php

namespace Clerk\Clerk\Controller\Adminhtml\Dashboard;

use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\View\Result\PageFactory;

class Search extends Action
{
    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerkLogger;

    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
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
            $resultPage->setActiveMenu('Clerk_Clerk::report_clerkroot_search_insights');
            $resultPage->addBreadcrumb(__('Clerk.io - Search Insights'), __('Clerk.io - Search Insights'));
            $resultPage->getConfig()->getTitle()->prepend(__('Clerk.io - Search Insights'));

            return $resultPage;
        } catch (Exception $e) {

            $this->clerkLogger->error('Search execute ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }
}
