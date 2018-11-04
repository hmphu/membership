<?php
/******************************************************
 * @package Magento 2 Membership
 * @author http://www.magefox.com
 * @copyright (C) 2018 - Magefox.Com
 * @license MIT
 *******************************************************/

namespace Magefox\Membership\Model;

class CustomerManagement implements \Magefox\Membership\Api\CustomerManagementInterface
{
    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaInterface
     */
    protected $searchCriteria;

    /**
     * @var \Magento\Framework\Api\Search\FilterGroup
     */
    protected $filterGroup;

    /**
     * @var \Magento\Framework\Api\Filter
     */
    protected $filter;

    /**
     * @var \Magento\Customer\Api\GroupManagementInterface
     */
    protected $groupManagement;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var \Magefox\Membership\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Magefox\Membership\Helper\Order
     */
    protected $orderHelper;

    public function __construct(
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        \Magento\Framework\Api\Filter $filter,
        \Magento\Customer\Api\GroupManagementInterface $groupManagement,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Magefox\Membership\Helper\Config $configHelper,
        \Magefox\Membership\Helper\Order $orderHelper
    ) {
        $this->customerRepository = $customerRepository;
        $this->searchCriteria = $searchCriteria;
        $this->filterGroup = $filterGroup;
        $this->filter = $filter;
        $this->groupManagement = $groupManagement;
        $this->dateTime = $dateTime;
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Calculate expiry date
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \DateTime
     * @throws \Exception
     */
    public function calculateExpiryDate(
        \Magento\Sales\Api\Data\OrderInterface $order
    ) {
        $now = new \DateTime(
            $this->dateTime->gmtDate(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT),
            new \DateTimeZone('UTC')
        );

        return $now->add($this->orderHelper->getPurchasedMembershipLength($order));
    }

    /**
     * Retrieve the Membership Group ID defined in product
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return int
     */
    public function getGroupId(
        \Magento\Sales\Api\Data\OrderInterface $order
    ) {
        return $this->orderHelper->getPurchasedMembershipGroupId($order);
    }

    /**
     * Make a given customer a Membership from a given order.
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \Magento\Customer\Model\Customer
     * @throws \Exception
     */
    public function invokeMembership(
        \Magento\Customer\Model\Customer $customer,
        \Magento\Sales\Api\Data\OrderInterface $order
    ) {
        if (!$this->orderHelper->canInvokeMembership($order)) {
            return $customer;
        }

        $expiry = $this->calculateExpiryDate($order)
            ->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT);

        $customerData = $customer->getDataModel();
        $customerData->setCustomAttribute('membership_expiry', $expiry)
            ->setCustomAttribute('membership_order_id', $order->getIncrementId())
            ->setGroupId($this->getGroupId($order));

        $customer->updateData($customerData)
            ->save();

        return $customer;
    }

    /**
     * Remove a given customers Membership.
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return \Magento\Customer\Model\Customer
     * @throws \Exception
     */
    public function revokeMembership(
        \Magento\Customer\Model\Customer $customer
    ) {
        $expiry = $this->dateTime->gmtDate(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT);

        $customerData = $customer->getDataModel();
        $customerData->setCustomAttribute('membership_expiry', $expiry)
            ->setGroupId($this->configHelper->getRevokeGroup());

        $customer->updateData($customerData)
            ->save();

        return $customer;
    }

    /**
     * Check customer is membership.
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return bool
     */
    public function isMembership(\Magento\Customer\Model\Customer $customer)
    {
        return $this->getDaysLeft($customer) > 0;
    }

    /**
     * Get expire time
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return string
     */
    public function getExpiry(\Magento\Customer\Model\Customer $customer)
    {
        return $customer->getData('membership_expiry');
    }

    /**
     * Get days to membership expire.
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return int
     */
    public function getDaysLeft(\Magento\Customer\Model\Customer $customer)
    {
        $expiry = new \DateTime($this->getExpiry($customer), new \DateTimeZone('UTC'));
        $today = new \DateTime(
            $this->dateTime->gmtDate(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT),
            new \DateTimeZone('UTC')
        );

        return $today->diff($expiry)->days;
    }
}
