<?php
/*
 * Copyright (C) 2015 Pay.nl
 */

namespace Paynl\Payment\Controller\Exchange;

/**
 * Description of Index
 *
 * @author Andy Pieters <andy@pay.nl>
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     *
     * @var \Paynl\Payment\Model\Config
     */
    protected $_config;

    /**
     *
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     *
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $_orderSender;

    public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Paynl\Payment\Model\Config $config,
    \Magento\Sales\Model\OrderFactory $orderFactory,
    \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    )
    {

        $this->_config       = $config;
        $this->_orderFactory = $orderFactory;
        $this->_orderSender  = $orderSender;
        parent::__construct($context);
    }

    public function execute()
    {
        $skipFraudDetection = false;
        \Paynl\Config::setApiToken($this->_config->getApiToken());

        $transaction = \Paynl\Transaction::getForExchange();
        $orderId     = $transaction->getDescription();
        $order       = $this->_orderFactory->create()->loadByIncrementId($orderId);

        if($order->getTotalDue() <= 0){
            die('TRUE| Payment already processed');
        }

        if ($transaction->isPaid()) {
            if ($order->getOrderCurrencyCode() != 'EUR') {
                $skipFraudDetection = true;
            }

            $payment = $order->getPayment();
            $payment->setTransactionId(
                $transaction->getId()
            );
            $payment->setCurrencyCode(
                $transaction->getPaidCurrency()
            );
            $payment->setIsTransactionClosed(
                0
            );
            $payment->registerCaptureNotification(
                $transaction->getPaidAmount(), $skipFraudDetection
            );
            $order->save();

            // notify customer
            $invoice = $payment->getCreatedInvoice();
            if ($invoice && !$order->getEmailSent()) {
                $this->_orderSender->send($order);
                $order->addStatusHistoryComment(
                    __('You notified customer about invoice #%1.',
                        $invoice->getIncrementId())
                )->setIsCustomerNotified(
                    true
                )->save();
            }

            die("TRUE| PAID");
        } elseif($transaction->isCanceled()){
            $order->cancel()->save();
        }

    }
}