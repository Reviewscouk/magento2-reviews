<?php

namespace Reviewscouk\Reviews\Helper;

/**
 * Normalises a Magento order creation timestamp to a UTC date (Y-m-d)
 * for the Reviews.io invitation order_date field.
 */
class OrderDate
{
    /**
     * @param string|null $createdAt Magento order created_at value
     * @return string|null UTC date (Y-m-d), or null when missing/invalid/future
     */
    public function fromCreatedAt(?string $createdAt): ?string
    {
        if ($createdAt === null || trim($createdAt) === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable(trim($createdAt), new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return null;
        }

        $dateString = $date->format('Y-m-d');
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');

        if ($dateString > $today) {
            return null;
        }

        return $dateString;
    }
}
