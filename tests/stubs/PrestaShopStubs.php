<?php
/**
 * Stand-ins for legacy PrestaShop classes, only what the tested services use.
 */

if (!class_exists('Order')) {
    class Order
    {
        /** @var array recorded addOrderPayment() calls: [amount, method, transactionId] */
        public static $addedPayments = [];

        /** @var string[] transaction ids that already exist on the order */
        public static $existingTransactionIds = [];

        public $id;

        public function __construct($id = null)
        {
            $this->id = $id;
        }

        public function getOrderPaymentCollection()
        {
            $payments = [];

            foreach (self::$existingTransactionIds as $transactionId) {
                $payment = new stdClass();
                $payment->transaction_id = $transactionId;
                $payments[] = $payment;
            }

            return $payments;
        }

        public function addOrderPayment($amount, $method = null, $transactionId = null)
        {
            self::$addedPayments[] = [$amount, $method, $transactionId];
            self::$existingTransactionIds[] = $transactionId;

            return true;
        }

        public static function reset(): void
        {
            self::$addedPayments = [];
            self::$existingTransactionIds = [];
        }
    }
}

if (!class_exists('Validate')) {
    class Validate
    {
        public static function isLoadedObject($object)
        {
            return is_object($object) && !empty($object->id);
        }
    }
}
