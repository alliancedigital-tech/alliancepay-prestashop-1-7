<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Callback;

use AlliancePay\Entity\AllianceScheduledPayment;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Entity\AllianceSubscriptionPlan;
use AlliancePay\Entity\AllianceSubscriptionAuditLog;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Service\Callback\CallbackProcessor;
use AlliancePay\Service\Subscription\Audit\AuditLogger;
use AlliancePay\Service\Subscription\Callback\SubscriptionCallbackProcessor;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class SubscriptionCallbackProcessorTest.
 */
class SubscriptionCallbackProcessorTest extends TestCase
{
    /** @var EntityManager */
    private $em;

    /** @var CallbackProcessor|MockObject */
    private $orderProcessor;

    /** @var SubscriptionCallbackProcessor */
    private $processor;

    private $subscriptionId;

    private $planId;

    protected function setUp(): void
    {
        Order::reset();

        $config = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/../../../src/Entity'], true, null, null, false);
        $this->em = EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $config);

        $classes = array_map([$this->em, 'getClassMetadata'], [
            AllianceSubscription::class,
            AllianceSubscriptionPlan::class,
            AllianceScheduledPayment::class,
            AllianceSubscriptionAuditLog::class,
        ]);
        (new SchemaTool($this->em))->createSchema($classes);

        $this->orderProcessor = $this->createMock(CallbackProcessor::class);
        $this->processor = new SubscriptionCallbackProcessor(
            $this->orderProcessor,
            new AuditLogger($this->createMock(AllianceLogger::class)),
            $this->createMock(AllianceLogger::class)
        );

        $rules = [
            ['paymentNumberFrom' => 1, 'paymentNumberTo' => 1, 'intervalValue' => 1, 'intervalUnit' => 'MONTH', 'coinAmount' => 19900, 'currency' => '980'],
            ['paymentNumberFrom' => 2, 'paymentNumberTo' => 999, 'intervalValue' => 1, 'intervalUnit' => 'MONTH', 'coinAmount' => 19900, 'currency' => '980'],
        ];

        $plan = (new AllianceSubscriptionPlan())
            ->setProviderPlanId('PLAN-1')->setProductId(10)->setPlanKey(str_repeat('a', 64))
            ->setMerchantId('M1')->setName('Monthly')->setCategoryIndicator('CAT')
            ->setRules($rules)->setCurrencyCode('980');
        $this->em->persist($plan);
        $this->em->flush();

        $subscription = (new AllianceSubscription())
            ->setOrderId(100)->setCustomerId(7)->setPlanId((int) $plan->getId())
            ->setRulesSnapshot($rules)->setProviderOrderId('H1')->setMerchantRequestId('REQ-1');
        $this->em->persist($subscription);
        $this->em->flush();

        $this->planId = (int) $plan->getId();
        $this->subscriptionId = (int) $subscription->getId();
    }

    private function deliver(array $payload): void
    {
        $this->processor->process($this->em, $this->subscriptionId, $payload + ['hppOrderId' => 'H1']);
        $this->em->clear();
    }

    private function subscription(): AllianceSubscription
    {
        return $this->em->find(AllianceSubscription::class, $this->subscriptionId);
    }

    private function plan(): AllianceSubscriptionPlan
    {
        return $this->em->find(AllianceSubscriptionPlan::class, $this->planId);
    }

    /**
     * @return AllianceScheduledPayment[]
     */
    private function rows(): array
    {
        return $this->em->getRepository(AllianceScheduledPayment::class)->findBy([], ['paymentNumber' => 'ASC', 'id' => 'ASC']);
    }

    private function operation(string $id, string $status = 'SUCCESS', int $amount = 19900, string $type = 'PURCHASE'): array
    {
        return ['operationId' => $id, 'type' => $type, 'status' => $status, 'coinAmount' => $amount];
    }

    private function recurring(array $extra): array
    {
        return $extra + ['recurringTransactionType' => 'RECURRING'];
    }

    public function testInitialPaymentKeepsTheSubscriptionPendingAndRunning(): void
    {
        $payload = ['orderStatus' => 'PENDING', 'operation' => $this->operation('OP1')];

        $this->orderProcessor->expects($this->exactly(2))->method('processCallback');

        $this->deliver($payload);
        $this->deliver($payload);

        $this->assertSame('PENDING', $this->subscription()->getStatus());
        $this->assertCount(1, $this->rows());
        $this->assertSame(1, $this->rows()[0]->getPaymentNumber());
        $this->assertSame('SUCCESS', $this->rows()[0]->getStatus());
        $this->assertSame([], Order::$addedPayments);
    }

    public function testInitialFailureDoesNotActivate(): void
    {
        $this->deliver(['orderStatus' => 'FAIL', 'operation' => $this->operation('OP1', 'FAIL')]);

        $this->assertSame('FAILED', $this->subscription()->getStatus());
    }

    public function testOrderSuccessAfterTheLastPaymentCompletesTheSubscription(): void
    {
        $this->deliver(['orderStatus' => 'PENDING', 'operation' => $this->operation('OP1')]);
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP2')]));

        $this->assertSame('COMPLETED', $this->subscription()->getStatus());
    }

    public function testPoorDeliveryFirstDoesNotBlockTheRichOneLater(): void
    {
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'orderStatus' => 'SUCCESS']));

        $this->assertSame([], Order::$addedPayments, 'no operation id and amount yet: nothing to book');
        $this->assertNull($this->rows()[0]->getOperationId());

        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP2')]));

        $row = $this->rows()[0];
        $this->assertSame('OP2', $row->getOperationId());
        $this->assertSame(19900, $row->getCoinAmount());
        $this->assertCount(1, Order::$addedPayments);
        $this->assertSame(199.0, Order::$addedPayments[0][0]);
        $this->assertSame('OP2', Order::$addedPayments[0][2]);
    }

    public function testPoorDeliveryAfterRichOneDoesNotWipeStoredFields(): void
    {
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP2')]));
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'orderStatus' => 'SUCCESS']));

        $row = $this->rows()[0];
        $this->assertSame('OP2', $row->getOperationId());
        $this->assertSame(19900, $row->getCoinAmount());
        $this->assertSame('SUCCESS', $row->getStatus());
        $this->assertCount(1, Order::$addedPayments);
    }

    public function testNullFieldsInALaterDeliveryDoNotOverwriteStoredCallbackData(): void
    {
        $this->deliver(['orderStatus' => 'SUCCESS', 'ecomOrderId' => 'ECOM-1', 'operation' => $this->operation('OP1')]);
        $this->deliver(['orderStatus' => 'SUCCESS', 'ecomOrderId' => null]);

        $this->assertSame('ECOM-1', $this->subscription()->getCallbackData()['ecomOrderId']);
    }

    public function testIdenticalRichDeliveryIsAppliedOnce(): void
    {
        $payload = $this->recurring(['scheduledPaymentId' => 'SP2', 'orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP2')]);

        $this->deliver($payload);
        $this->deliver($payload);
        $this->deliver($payload);

        $this->assertCount(1, $this->rows());
        $this->assertCount(1, Order::$addedPayments);
    }

    public function testMissingRecurringRowIsCreatedNotDropped(): void
    {
        $this->deliver(['orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP1')]);
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP-NEW', 'orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP2')]));

        $rows = $this->rows();
        $this->assertCount(2, $rows);
        $this->assertSame('SP-NEW', $rows[1]->getScheduledPaymentId());
        $this->assertSame(2, $rows[1]->getPaymentNumber(), 'numbered after the initial payment');
    }

    public function testAmountMismatchOnlyFlagsTheRecordAndKeepsThePayment(): void
    {
        $this->deliver(['orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP1')]);
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP2', 'SUCCESS', 15000)]));

        $row = $this->rows()[1];
        $this->assertSame('SUCCESS', $row->getStatus());
        $this->assertTrue($row->isReviewRequired());
        $this->assertStringContainsString('19900', (string) $row->getReviewNote());
        $this->assertCount(1, Order::$addedPayments, 'money is already taken: the payment is still booked');
    }

    public function testMatchingAmountIsNotFlagged(): void
    {
        $this->deliver(['orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP1')]);
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP2')]));

        $this->assertFalse($this->rows()[1]->isReviewRequired());
    }

    public function testFailedThenRetriedThenLateDuplicateOfTheOldOperation(): void
    {
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $this->operation('OP-A', 'FAIL')]));
        $this->assertSame('FAIL', $this->rows()[0]->getStatus());
        $this->assertSame(1, $this->rows()[0]->getRetryCount());

        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $this->operation('OP-B', 'SUCCESS')]));
        $this->assertSame('SUCCESS', $this->rows()[0]->getStatus());
        $this->assertSame('OP-B', $this->rows()[0]->getOperationId());

        // A delayed delivery of the first (failed) operation must not roll the payment back.
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $this->operation('OP-A', 'FAIL')]));

        $row = $this->rows()[0];
        $this->assertSame('SUCCESS', $row->getStatus());
        $this->assertSame('OP-B', $row->getOperationId());
        $this->assertSame(1, $row->getRetryCount());
        $this->assertCount(1, Order::$addedPayments);
    }

    public function testTerminalStatusIsNotRolledBackByLateIntermediateOne(): void
    {
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $this->operation('OP2', 'SUCCESS')]));
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $this->operation('OP2', 'PENDING')]));

        $this->assertSame('SUCCESS', $this->rows()[0]->getStatus());
    }

    public function testPendingThenSuccessOfTheSameOperationIsAppliedInOrder(): void
    {
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $this->operation('OP2', 'PENDING')]));
        $this->assertSame('PROCESSING', $this->rows()[0]->getStatus());
        $this->assertSame([], Order::$addedPayments);

        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $this->operation('OP2', 'SUCCESS')]));
        $this->assertSame('SUCCESS', $this->rows()[0]->getStatus());
        $this->assertCount(1, Order::$addedPayments);
    }

    /**
     * @dataProvider rareStatuses
     */
    public function testRareScheduleStatusesAreHandledExplicitly(string $raw, string $expected): void
    {
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'scheduledPaymentStatus' => $raw]));

        $this->assertSame($expected, $this->rows()[0]->getStatus());
        $this->assertFalse($this->rows()[0]->isReviewRequired());
    }

    public function rareStatuses(): array
    {
        return [
            ['SKIPPED_PAUSED', 'SKIPPED_PAUSED'],
            ['INVALIDATED', 'INVALIDATED'],
            ['RETRY', 'RETRY'],
            ['PROCESSING', 'PROCESSING'],
        ];
    }

    public function testUnknownStatusIsFlaggedNotSilentlyIgnored(): void
    {
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'scheduledPaymentStatus' => 'SOMETHING_NEW']));

        $this->assertTrue($this->rows()[0]->isReviewRequired());
        $this->assertStringContainsString('SOMETHING_NEW', (string) $this->rows()[0]->getReviewNote());
    }

    public function testInitialCallbackCarryingTheScheduleCreatesWaitingRows(): void
    {
        $this->deliver([
            'orderStatus' => 'SUCCESS',
            'operation' => $this->operation('OP1'),
            'scheduledPayments' => [
                ['scheduledPaymentId' => 'SP2', 'scheduledDate' => '2026-11-01T00:00:00Z', 'coinAmount' => 19900],
                ['scheduledPaymentId' => 'SP3', 'scheduledDate' => '2026-12-01T00:00:00Z', 'coinAmount' => 19900],
            ],
        ]);

        $rows = $this->rows();
        $this->assertCount(3, $rows);
        $this->assertSame('WAITING', $rows[1]->getStatus());
        $this->assertSame('2026-11-01', $rows[1]->getScheduledDate()->format('Y-m-d'));
        $this->assertSame('SP3', $rows[2]->getScheduledPaymentId());
    }

    public function testScheduleListIsIdempotentAndNeverResetsPaymentStatus(): void
    {
        $schedule = ['scheduledPayments' => [['scheduledPaymentId' => 'SP2', 'scheduledDate' => '2026-11-01T00:00:00Z']]];

        $this->deliver(['orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP1')] + $schedule);
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $this->operation('OP2')]));
        $this->deliver(['orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP1')] + $schedule);

        $this->assertCount(2, $this->rows());
        $this->assertSame('SUCCESS', $this->rows()[1]->getStatus());
    }

    public function testProviderCancelOfARunningSubscription(): void
    {
        $this->deliver(['orderStatus' => 'PENDING', 'operation' => $this->operation('OP1')]);

        $this->deliver($this->recurring(['subscriptionStatus' => 'CANCELED']));
        $this->deliver($this->recurring(['subscriptionStatus' => 'CANCELED']));

        $this->assertSame('CANCELED', $this->subscription()->getStatus());
    }

    public function testFailedRecurringPaymentDoesNotCancelTheSubscription(): void
    {
        $this->deliver(['orderStatus' => 'PENDING', 'operation' => $this->operation('OP1')]);
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'orderStatus' => 'CANCELED', 'operation' => $this->operation('OP2', 'FAIL')]));

        $this->assertSame('PENDING', $this->subscription()->getStatus());
    }

    public function testRefundOperationsAreNotBookedAsPayments(): void
    {
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $this->operation('OP-R', 'SUCCESS', 19900, 'REFUND')]));

        $this->assertSame([], Order::$addedPayments);
        $this->assertSame([], $this->rows());
    }

    public function testAmountInAnotherCurrencyIsNotComparedToTheSnapshot(): void
    {
        $this->deliver(['orderStatus' => 'SUCCESS', 'operation' => $this->operation('OP1')]);
        $operation = $this->operation('OP2', 'SUCCESS', 550) + ['conversionRate' => 40.0];
        $this->deliver($this->recurring(['scheduledPaymentId' => 'SP2', 'operation' => $operation]));

        $this->assertFalse($this->rows()[1]->isReviewRequired());
    }
}
