<?php

namespace NFePHP\DA\Tests;

use DOMDocument;
use NFePHP\DA\MDFe\Damdfe;

class DamdfeValePedagioTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider quantidadesDeVales
     */
    public function testImprimeTodosOsValesPedagio($quantidade, $comCiot)
    {
        $dom = new DOMDocument();
        $dom->load(__DIR__ . '/fixtures/xml/mdfe_modelo_nao_valido.xml');
        $infANTT = $dom->getElementsByTagName('infANTT')->item(0);
        $namespace = $infANTT->namespaceURI;
        if ($comCiot) {
            $ciot = $infANTT->appendChild($dom->createElementNS($namespace, 'infCIOT'));
            $ciot->appendChild($dom->createElementNS($namespace, 'CIOT', '123456789012'));
            $ciot->appendChild($dom->createElementNS($namespace, 'CNPJ', '11111111000111'));
        }
        $esperados = [];
        if ($quantidade > 0) {
            $valePed = $infANTT->appendChild($dom->createElementNS($namespace, 'valePed'));
            for ($i = 1; $i <= $quantidade; $i++) {
                $disp = $valePed->appendChild($dom->createElementNS($namespace, 'disp'));
                $cnpjFornecedor = '222222220000' . sprintf('%02d', $i);
                $compra = (string) (900000000 + $i);
                $disp->appendChild($dom->createElementNS($namespace, 'CNPJForn', $cnpjFornecedor));
                if ($i % 2 === 0) {
                    $disp->appendChild($dom->createElementNS($namespace, 'CPFPg', '12345678901'));
                    $esperados[] = '123.456.789-01';
                } else {
                    $disp->appendChild($dom->createElementNS($namespace, 'CNPJPg', '33333333000133'));
                    $esperados[] = '33.333.333/0001-33';
                }
                $disp->appendChild($dom->createElementNS($namespace, 'nCompra', $compra));
                $disp->appendChild($dom->createElementNS($namespace, 'vValePed', '100.00'));
                $esperados[] = '22.222.222/0000-' . sprintf('%02d', $i);
                $esperados[] = $compra;
            }
        }

        // Mantém o conteúdo textual do PDF legível para conferir a saída real.
        $damdfe = new class($dom->saveXML()) extends Damdfe {
            public function buildMDFe()
            {
                parent::buildMDFe();
                $this->pdf->setCompression(false);
            }
        };
        $damdfe->setDefaultFont('helvetica');
        $pdf = $damdfe->render();

        $this->assertStringStartsWith('%PDF-', $pdf);
        foreach ($esperados as $texto) {
            $this->assertTrue(
                strpos($pdf, '(' . $texto . ') Tj') !== false,
                'Dado de vale-pedágio ausente: ' . $texto
            );
        }
        if ($quantidade === 0) {
            $this->assertNotContains('(N' . chr(186) . ' Comprovante) Tj', $pdf);
        }
        if ($comCiot) {
            $this->assertContains('(123456789012) Tj', $pdf);
        }
    }

    public function quantidadesDeVales()
    {
        return [
            'sem vale' => [0, false],
            'um vale' => [1, false],
            'dois vales' => [2, false],
            'tres vales' => [3, false],
            'dez vales' => [10, false],
            'dois vales e CIOT' => [2, true],
        ];
    }
}
