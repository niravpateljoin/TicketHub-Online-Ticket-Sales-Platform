<?php

namespace App\Service\Cache;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Facade over all application Redis cache pools.
 *
 * Key naming convention:
 *   events:list:{filterHash}:{page}     → public event listing  (TTL 5 min)
 *   events:detail:{id}                  → single event detail   (TTL 5 min)
 *   categories:all                      → all categories        (TTL 1 h)
 *   admin:stats                         → admin system stats    (TTL 2 min)
 *   organizer:stats:{organizerId}       → organizer summary     (TTL 2 min)
 *   organizer:revenue:{eventId}         → per-event revenue     (TTL 2 min)
 *
 * Tag convention:
 *   events_list            → all paginated event listings
 *   event_{id}             → specific event (detail + listings that include it)
 *   categories             → category list
 *
 * Rules:
 *   - availableSeats is NEVER served from cache — always refreshed from DB.
 *   - User-specific and financial data (cart, balance, bookings) are never cached.
 */
class CacheService
{
    public function __construct(
        #[Autowire(service: 'events_list_pool')]    private readonly TagAwareCacheInterface $eventsListPool,
        #[Autowire(service: 'event_detail_pool')]   private readonly TagAwareCacheInterface $eventDetailPool,
        #[Autowire(service: 'categories_pool')]     private readonly TagAwareCacheInterface $categoriesPool,
        #[Autowire(service: 'admin_stats_pool')]    private readonly CacheInterface $adminStatsPool,
        #[Autowire(service: 'organizer_stats_pool')] private readonly TagAwareCacheInterface $organizerStatsPool,
    ) {}

    // ── Read methods ──────────────────────────────────────────────────────────

    /**
     * Cache key: events:list:{filterHash}:{page}
     * Tags: [events_list]
     */
    public function getEventsList(string $filterHash, int $page, int $perPage, callable $callback): mixed
    {
        $key = sprintf('events_list_%s_%d_%d', $filterHash, $page, $perPage);

        return $this->eventsListPool->get($key, function (ItemInterface $item) use ($callback) {
            $item->expiresAfter(300);
            $item->tag(['events_list']);
            return $callback();
        });
    }

    /**
     * Cache key: events_detail_{id}
     * Tags: [events_list, event_{id}]
     * NOTE: caller must refresh availableSeats from DB after this call.
     */
    public function getEventDetail(int $id, callable $callback): mixed
    {
        $key = sprintf('events_detail_%d', $id);

        return $this->eventDetailPool->get($key, function (ItemInterface $item) use ($id, $callback) {
            $item->expiresAfter(300);
            $item->tag(['events_list', "event_{$id}"]);
            return $callback();
        });
    }

    /**
     * Cache key: categories_all
     * Tags: [categories]
     */
    public function getCategories(callable $callback): mixed
    {
        return $this->categoriesPool->get('categories_all', function (ItemInterface $item) use ($callback) {
            $item->expiresAfter(3600);
            $item->tag(['categories']);
            return $callback();
        });
    }

    /**
     * Cache key: admin_stats
     */
    public function getAdminStats(callable $callback): mixed
    {
        return $this->adminStatsPool->get('admin_stats', function (ItemInterface $item) use ($callback) {
            $item->expiresAfter(120);
            return $callback();
        });
    }

    /**
     * Cache key: organizer_stats_{organizerId}
     * Tags: [organizer_{organizerId}]
     */
    public function getOrganizerStats(int $organizerId, callable $callback): mixed
    {
        $key = sprintf('organizer_stats_%d', $organizerId);

        return $this->organizerStatsPool->get($key, function (ItemInterface $item) use ($organizerId, $callback) {
            $item->expiresAfter(120);
            $item->tag(["organizer_{$organizerId}"]);
            return $callback();
        });
    }

    /**
     * Cache key: organizer_revenue_{eventId}
     * Tags: [event_{eventId}, organizer_{organizerId}]
     */
    public function getOrganizerEventRevenue(int $eventId, int $organizerId, callable $callback): mixed
    {
        $key = sprintf('organizer_revenue_%d', $eventId);

        return $this->organizerStatsPool->get($key, function (ItemInterface $item) use ($eventId, $organizerId, $callback) {
            $item->expiresAfter(120);
            $item->tag(["event_{$eventId}", "organizer_{$organizerId}"]);
            return $callback();
        });
    }

    // ── Invalidation methods ─────────────────────────────────────────────────

    /** Bust all paginated event listings (e.g. soldCount changed). */
    public function invalidateEventsList(): void
    {
        $this->eventsListPool->invalidateTags(['events_list']);
    }

    /** Bust the cached detail for one event. */
    public function invalidateEventDetail(int $id): void
    {
        $this->eventDetailPool->invalidateTags(["event_{$id}"]);
    }

    /** Bust both listing and detail caches for a given event (covers create/update/cancel). */
    public function invalidateEvent(int $id): void
    {
        $this->invalidateEventsList();
        $this->invalidateEventDetail($id);
        // Also bust organizer revenue cache for this event
        $this->organizerStatsPool->invalidateTags(["event_{$id}"]);
    }

    /** Bust all organizer summary stats for a given organizer. */
    public function invalidateOrganizerStats(int $organizerId): void
    {
        $this->organizerStatsPool->invalidateTags(["organizer_{$organizerId}"]);
    }

    /** Bust the category list. */
    public function invalidateCategories(): void
    {
        $this->categoriesPool->invalidateTags(['categories']);
    }

    /** Bust admin stats (called after any booking that changes system totals). */
    public function invalidateAdminStats(): void
    {
        $this->adminStatsPool->delete('admin_stats');
    }
}
