<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Management\Command\UpdateDomainMetadataCommand;
use Maatify\I18n\Management\Command\UpdateScopeMetadataCommand;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;

final class RepositoryMutationReturnContractTest extends MysqlIntegrationTestCase
{
    public function testGenericCommandMutationsReportAffectedRows(): void
    {
        $scopeId = $this->scalarInt("SELECT id FROM maa_i18n_scopes WHERE code = 'ct'");
        $domainId = $this->scalarInt("SELECT id FROM maa_i18n_domains WHERE code = 'home'");
        $keyId = $this->createKey('ct', 'home', 'description', 'original');

        self::assertTrue($this->scopes->updateMetadata(new UpdateScopeMetadataCommand($scopeId, 'Website renamed')));
        self::assertFalse($this->scopes->updateMetadata(new UpdateScopeMetadataCommand($scopeId, 'Website renamed')));
        self::assertFalse($this->scopes->updateMetadata(new UpdateScopeMetadataCommand(999999, 'Missing')));
        self::assertTrue($this->scopes->setActive($scopeId, false));
        self::assertFalse($this->scopes->setActive($scopeId, false));
        self::assertFalse($this->scopes->setActive(999999, false));

        self::assertTrue($this->domains->updateMetadata(new UpdateDomainMetadataCommand($domainId, 'Home renamed')));
        self::assertFalse($this->domains->updateMetadata(new UpdateDomainMetadataCommand($domainId, 'Home renamed')));
        self::assertFalse($this->domains->updateMetadata(new UpdateDomainMetadataCommand(999999, 'Missing')));
        self::assertTrue($this->domains->setActive($domainId, false));
        self::assertFalse($this->domains->setActive($domainId, false));
        self::assertFalse($this->domains->setActive(999999, false));

        self::assertTrue($this->keys->updateDescription($keyId, 'updated'));
        self::assertFalse($this->keys->updateDescription($keyId, 'updated'));
        self::assertFalse($this->keys->updateDescription(999999, 'missing'));

        // The public Services keep successful idempotent requests successful.
        $this->scopeManagement->updateMetadata(new UpdateScopeMetadataCommand($scopeId, 'Website renamed'));
        $this->scopeManagement->setActive($scopeId, false);
        $this->domainManagement->updateMetadata(new UpdateDomainMetadataCommand($domainId, 'Home renamed'));
        $this->domainManagement->setActive($domainId, false);
        $this->writer->updateKeyDescription($keyId, 'updated');
    }

    public function testDeleteMutationsReportBooleanResultsAndKeepServiceDerivedState(): void
    {
        $keyId = $this->createKey('ct', 'home', 'repository-delete');
        $this->upsert('ar', $keyId, 'stored', null);

        self::assertTrue($this->translations->deleteByLanguageAndKey('ar', $keyId));
        self::assertFalse($this->translations->deleteByLanguageAndKey('ar', $keyId));

        $serviceKeyId = $this->createKey('ct', 'home', 'service-delete');
        $this->upsert('ar', $serviceKeyId, 'stored', null);
        self::assertNotNull($this->summary->getRow('ct', 'home', 'ar'));

        $this->writer->deleteTranslation('ar', $serviceKeyId);

        self::assertNull($this->translations->getByLanguageAndKey('ar', $serviceKeyId));
        self::assertNull($this->summary->getRow('ct', 'home', 'ar'));

        self::assertTrue($this->domainScopes->unassign('ct', 'home'));
        self::assertFalse($this->domainScopes->unassign('ct', 'home'));
    }
}
