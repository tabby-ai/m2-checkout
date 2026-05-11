<?php
namespace Tabby\Checkout\Gateway\Response;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;
use Tabby\Checkout\Gateway\Helper\Transaction as TransactionHelper;

class FetchTransactionInfoHandler implements HandlerInterface
{
    /*
     * var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /*
     * var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Handles transaction id
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::ReadPayment($handlingSubject);

        $payment = $paymentDO->getPayment();
        $transactionId = $handlingSubject['transactionId'];
        
        $txn = $payment->getAuthorizationTransaction();
        $raw = [];
        if ($txn->getTxnId() == $transactionId) {
            $raw = $response;
            unset($raw['order_history']);
            unset($raw['meta']);
        } else {
            // load child transaction
            $txn = $this->getTransaction($transactionId, $payment->getOrder()->getId());

            // search transaction in captures and refunds
            foreach ($response['captures'] as $capture) {
                if ($capture['id'] != $transactionId) {
                    continue;
                }
                $raw = $capture;
            }
            foreach ($response['refunds'] as $refund) {
                if ($refund['id'] != $transactionId) {
                    continue;
                }
                $raw = $refund;
            }
        }
    
        if ($txn) {
            $txn->setAdditionalInformation(
                Transaction::RAW_DETAILS,
                TransactionHelper::packPaymentDetails($raw)
            );
            $txn->save();
        }

    }
    protected function getTransaction($txnId, $order_id) {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('txn_id', $txnId)
            ->addFilter('order_id', $order_id)
            ->create();

        $transactions = $this->transactionRepository->getList($searchCriteria)->getItems();

        return reset($transactions);
    }
}
