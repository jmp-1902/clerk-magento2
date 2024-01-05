<?php

namespace Clerk\Clerk\Controller\Cart;

use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Exception;
use Magento\Catalog\Controller\Product;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\View\Result\PageFactory;

class Added extends Product
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
     * Added constructor.
     *
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
        $this->resultPageFactory = $resultPageFactory;
        $this->clerkLogger = $clerkLogger;
        parent::__construct($context);
    }


    /**
     * Dispatch request
     *
     * @return ResultInterface|null
     * @throws FileSystemException
     */
    public function execute(): ?ResultInterface
    {
        try {

            $product = $this->_initProduct();

            if (!$product) {
                //Redirect to frontpage
                $this->_redirect('/');
                return null;
            }

            return $this->resultPageFactory->create();

        } catch (Exception $e) {

            $this->clerkLogger->error('Cart execute ERROR', ['error' => $e->getMessage()]);

        }
        return null;
    }
}
