<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Vorstoss;
use OCA\ParliamentWinterthur\Db\VorstossMapper;
use OCA\ParliamentWinterthur\Service\VorstossImportService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class VorstossImportServiceTest extends TestCase
{
    public function testTitelAusDateinameEntferntEndung(): void
    {
        $service = new VorstossImportService(
            $this->createStub(IRootFolder::class),
            $this->createStub(VorstossMapper::class),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertSame('Motion Velowege', $service->titelAusDateiname('Motion Velowege.pdf'));
        $this->assertSame('Ohne Endung', $service->titelAusDateiname('Ohne Endung'));
    }

    private function rootMitDatei(string $dateiname): IRootFolder
    {
        $datei = $this->createStub(File::class);
        $datei->method('getName')->willReturn($dateiname);

        $eigeneOrdner = $this->createStub(Folder::class);
        $eigeneOrdner->method('getDirectoryListing')->willReturn([$datei]);

        $userFolder = $this->createStub(Folder::class);
        $userFolder->method('nodeExists')->willReturnCallback(
            static fn (string $p): bool => str_contains($p, '10_Eigene')
        );
        $userFolder->method('get')->willReturn($eigeneOrdner);

        $root = $this->createStub(IRootFolder::class);
        $root->method('getUserFolder')->willReturn($userFolder);
        return $root;
    }

    public function testImportiereLegtNeuesDokumentAlsEigenenVorstossAn(): void
    {
        $mapper = $this->createMock(VorstossMapper::class);
        $mapper->method('findByDokument')->willReturn(null);
        $mapper->expects($this->once())
            ->method('insert')
            ->with($this->callback(static fn (Vorstoss $v): bool =>
                $v->getHerkunft() === 'eigene'
                && $v->getTitel() === 'Postulat X'
                && str_contains($v->getDokument(), '10_Eigene/Postulat X.pdf')
                && $v->getStatus() === 'neu'))
            ->willReturnArgument(0);

        $anzahl = (new VorstossImportService(
            $this->rootMitDatei('Postulat X.pdf'),
            $mapper,
            $this->createStub(LoggerInterface::class),
        ))->importiere();

        $this->assertSame(1, $anzahl);
    }

    public function testImportiereUeberspringtBereitsImportierte(): void
    {
        $mapper = $this->createMock(VorstossMapper::class);
        $mapper->method('findByDokument')->willReturn(new Vorstoss());
        $mapper->expects($this->never())->method('insert');

        $anzahl = (new VorstossImportService(
            $this->rootMitDatei('Postulat X.pdf'),
            $mapper,
            $this->createStub(LoggerInterface::class),
        ))->importiere();

        $this->assertSame(0, $anzahl);
    }
}
