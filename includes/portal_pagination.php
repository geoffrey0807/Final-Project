<?php
function renderPortalPagination(int $currentPage, int $totalItems, int $itemsPerPage, array $queryParameters = []): void
{
    $totalPages = (int) ceil($totalItems / $itemsPerPage);
    if ($totalPages < 2) {
        return;
    }

    $baseUrl = $_SERVER['PHP_SELF'] ?? '';
    $pageUrl = static function (int $page) use ($baseUrl, $queryParameters): string {
        $parameters = array_merge($queryParameters, ['page' => $page]);
        return htmlspecialchars($baseUrl . '?' . http_build_query($parameters), ENT_QUOTES, 'UTF-8');
    };

    $firstItem = (($currentPage - 1) * $itemsPerPage) + 1;
    $lastItem = min($currentPage * $itemsPerPage, $totalItems);
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    ?>
    <nav class="portal-pagination" aria-label="Pagination">
        <span class="portal-pagination-summary">
            Showing <?= number_format($firstItem) ?>–<?= number_format($lastItem) ?> of <?= number_format($totalItems) ?>
        </span>
        <div class="portal-pagination-pages">
            <?php if ($currentPage > 1): ?>
                <a class="portal-pagination-link" href="<?= $pageUrl($currentPage - 1) ?>" rel="prev">Previous</a>
            <?php endif; ?>
            <?php if ($startPage > 1): ?>
                <a class="portal-pagination-link" href="<?= $pageUrl(1) ?>">1</a>
                <?php if ($startPage > 2): ?><span class="portal-pagination-ellipsis" aria-hidden="true">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                <?php if ($page === $currentPage): ?>
                    <span class="portal-pagination-link is-current" aria-current="page"><?= $page ?></span>
                <?php else: ?>
                    <a class="portal-pagination-link" href="<?= $pageUrl($page) ?>"><?= $page ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?><span class="portal-pagination-ellipsis" aria-hidden="true">…</span><?php endif; ?>
                <a class="portal-pagination-link" href="<?= $pageUrl($totalPages) ?>"><?= $totalPages ?></a>
            <?php endif; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a class="portal-pagination-link" href="<?= $pageUrl($currentPage + 1) ?>" rel="next">Next</a>
            <?php endif; ?>
        </div>
    </nav>
    <?php
}
