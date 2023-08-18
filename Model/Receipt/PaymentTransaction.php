<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilderInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Logger\PaytrailLogger;

class PaymentTransaction
{
    /**
     * PaymentTransaction constructor.
     *
     * @param TransactionBuilderInterface $transactionBuilder
     * @param ApiData $apiData
     * @param CancelOrderService $cancelOrderService
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param PaytrailLogger $paytrailLogger
     */
    public function __construct(
        private TransactionBuilderInterface $transactionBuilder,
        private ApiData                     $apiData,
        private CancelOrderService          $cancelOrderService,
        private OrderRepositoryInterface    $orderRepositoryInterface,
        private PaytrailLogger $paytrailLogger
    ) {
    }

    /**
     * AddPaymentTransaction function
     *
     * @param Order $order
     * @param $transactionId
     * @param array $details
     * @return \Magento\Sales\Api\Data\TransactionInterface
     */
    public function addPaymentTransaction(Order $order, $transactionId, array $details = [])
    {
        /** @var \Magento\Framework\DataObject|\Magento\Sales\Api\Data\OrderPaymentInterface |mixed|null $payment */
        $payment = $order->getPayment();

        /** @var \Magento\Sales\Api\Data\TransactionInterface $transaction */
        $transaction = $this->transactionBuilder
            ->setPayment($payment)->setOrder($order)
            ->setTransactionId($transactionId)
            ->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $details])
            ->setFailSafe(true)
            ->build(Transaction::TYPE_CAPTURE);
        $transaction->setIsClosed(0);
        return $transaction;
    }

    /**
     * VerifyPaymentData function
     * 
     * @param $params
     * @param $currentOrder
     * @return mixed|string|void
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    public function verifyPaymentData($params, $currentOrder)
    {
        $status = $params['checkout-status'];
        $verifiedPayment = $this->apiData->validateHmac($params, $params['signature']);

        if ($verifiedPayment && ($status === 'ok' || $status == 'pending' || $status == 'delayed')) {
            return $status;
        } else {
            $currentOrder->addCommentToStatusHistory(__('Failed to complete the payment.'));
            $this->orderRepositoryInterface->save($currentOrder);
            $this->cancelOrderService->cancelOrderById($currentOrder->getId());

            $this->paytrailLogger->logData(
                \Monolog\Logger::ERROR,
                'Failed to complete the payment. Please try again or contact the customer service.'
            );
            throw new CheckoutException(
                __('Failed to complete the payment. Please try again or contact the customer service.')
            );
        }
    }
}
