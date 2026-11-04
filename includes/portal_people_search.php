<?php
$peopleSearchScope = match ($activeUserRole) {
    'admin' => 'All roles and account states',
    'registrar' => 'All school accounts',
    'dean' => 'Professors only',
    'professor' => 'Students in your classes',
    default => '',
};
?>
<section class="portal-people-search-panel" id="portalPeopleSearchPanel" data-people-search-panel
    data-endpoint="<?= htmlspecialchars($siteRootPath . 'api/portal_people_search.php', ENT_QUOTES, 'UTF-8') ?>" hidden>
    <div class="portal-people-search-heading">
        <h2>Search people</h2>
    </div>
    <label class="portal-people-search-field">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="10.8" cy="10.8" r="6.8" />
            <path d="m16 16 5 5" />
        </svg>
        <input type="search" data-people-search-input autocomplete="off"
            placeholder="Search name, ID number, email, role, or status..."
            aria-label="Search people by name, account ID, email, role, or status">
    </label>
    <p class="portal-people-search-scope"><?= htmlspecialchars($peopleSearchScope, ENT_QUOTES, 'UTF-8') ?></p>
    <div class="portal-people-search-results" data-people-search-results role="list" aria-live="polite">
        <p class="portal-people-search-message" data-people-search-message>Enter at least 2 characters.</p>
    </div>
</section>