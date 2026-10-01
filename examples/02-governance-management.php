<?php

/**
 * Example 02 - Governance management.
 *
 * Capability: scopes, domains and their assignment: create, metadata, active
 * state, code change, ordering, assignment, and the governance failures a Host
 * has to handle.
 *
 *   Input -> Public Call -> Result -> Boundary
 *   Command / id / code -> management service -> void / id / typed exception
 *   -> one I18n transaction per call (or yours, when one is already open)
 *
 * Usage guide: docs/guides/USAGE_GUIDE.md (section "Govern scopes and domains")
 */

declare(strict_types=1);

require __DIR__ . '/_support/bootstrap.php';
require __DIR__ . '/_support/ExampleCore.php';

use Maatify\I18n\Exception\DomainInUseException;
use Maatify\I18n\Exception\DomainScopeAlreadyAssignedException;
use Maatify\I18n\Exception\DomainScopeViolationException;
use Maatify\I18n\Exception\ScopeNotAllowedException;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpdateScopeMetadataCommand;
use Maatify\I18n\Management\Criteria\DomainListCriteria;
use Maatify\I18n\Management\Criteria\ScopeListCriteria;

echo 'Example 02 - Governance management', PHP_EOL;

$core = ExampleCore::from(example_pdo());

// Create: the display position is never an input, it is appended.
$webId = $core->scopes->create(new CreateScopeCommand('web', 'Website'));
$adminId = $core->scopes->create(new CreateScopeCommand('admin', 'Admin panel', 'Back office'));
$core->domains->create(new CreateDomainCommand('home', 'Home page'));
$core->domains->create(new CreateDomainCommand('auth', 'Authentication'));

// Metadata: null leaves a field untouched, at least one field must change.
$core->scopes->updateMetadata(new UpdateScopeMetadataCommand($webId, name: 'Public website'));
example_expect('metadata changed', 'Public website', $core->managementRead->getScope($webId)->name);

// Ordering: moveToPosition is delegated to maatify/persistence.
$core->scopes->moveToPosition($adminId, 1);
$page = $core->managementRead->searchScopes(new ScopeListCriteria());
example_expect(
    'scopes list in display order',
    ['admin', 'web'],
    array_map(static fn($scope): string => $scope->code, $page->data),
);

// Active state: an inactive scope refuses new governed usage.
$core->assignments->assign('web', 'home');
$core->scopes->setActive($webId, false);
example_expect_throws(
    'a key in an inactive scope is refused',
    ScopeNotAllowedException::class,
    static fn() => $core->writer->createKey(new CreateKeyCommand('web', 'home', 'title')),
);
$core->scopes->setActive($webId, true);

// Assignment: duplicate and missing assignments are typed failures.
example_expect_throws(
    'assigning twice is refused',
    DomainScopeAlreadyAssignedException::class,
    static fn() => $core->assignments->assign('web', 'home'),
);
example_expect_throws(
    'a domain that is not assigned to the scope is refused',
    DomainScopeViolationException::class,
    static fn() => $core->writer->createKey(new CreateKeyCommand('web', 'auth', 'login')),
);
example_expect('assignment is observable', true, $core->managementRead->isDomainAssigned('web', 'home'));

// Code change: allowed only while the code is unused.
$authId = $core->managementRead->searchDomains(
    new DomainListCriteria(code: 'auth'),
)->data[0]->id;
$core->domains->changeCode($authId, 'signin');
example_expect('an unused domain code can change', 'signin', $core->managementRead->getDomain($authId)->code);

$core->writer->createKey(new CreateKeyCommand('web', 'home', 'title'));
$homeId = $core->managementRead->searchDomains(
    new DomainListCriteria(code: 'home'),
)->data[0]->id;
example_expect_throws(
    'a used domain code cannot change',
    DomainInUseException::class,
    static fn() => $core->domains->changeCode($homeId, 'landing'),
);

// Unassign, then a selector-friendly read of what is assigned.
$core->assignments->unassign('web', 'home');
example_expect(
    'nothing is assigned to web any more',
    true,
    $core->managementRead->listDomainOptionsForScope('web')->isEmpty(),
);
