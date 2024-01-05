<?php

namespace Clerk\Clerk\Controller\Powerstep;

use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\FileSystemException;

class Popup extends Action
{
    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerkLogger;
    /**
     * @var Session
     */
    protected Session $checkoutSession;

    public function __construct(
        Context     $context,
        Session     $checkoutSession,
        ClerkLogger $clerkLogger
    )
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->clerkLogger = $clerkLogger;
    }

    /**
     * Dispatch request
     *
     * @return void
     * @throws FileSystemException
     */
    public function execute(): void
    {
        try {

            $response = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
            $layout = $response->addHandle('clerk_clerk_powerstep_popup')->getLayout();

            $response = $layout->getBlock('page.block')->toHtml();
            $this->getResponse()->setBody($response);
            return;

        } catch (Exception $e) {
            $this->clerkLogger->error('Powerstep execute ERROR', ['error' => $e->getMessage()]);
        }
    }
}
