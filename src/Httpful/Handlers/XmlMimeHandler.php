<?php

declare(strict_types=1);

namespace Httpful\Handlers;

use Httpful\Exception\XmlParseException;

/**
 * Mime Type: application/xml
 */
class XmlMimeHandler extends DefaultMimeHandler
{
    /**
     * @var string xml namespace to use with simple_load_string
     */
    private $namespace;

    /**
     * @var int see http://www.php.net/manual/en/libxml.constants.php
     */
    private $libxml_opts;

    /**
     * @param array<string, mixed> $conf sets configuration options
     */
    public function __construct(array $conf = [])
    {
        parent::__construct($conf);

        $this->namespace = $conf['namespace'] ?? '';
        $this->libxml_opts = $conf['libxml_opts'] ?? 0;
    }

    /**
     * @param string $body
     *
     * @return \SimpleXMLElement|null
     */
    public function parse($body)
    {
        $body = $this->stripBom($body);
        if (empty($body)) {
            return null;
        }

        $parsed = \simplexml_load_string($body, \SimpleXMLElement::class, $this->libxml_opts, $this->namespace);
        if ($parsed === false) {
            throw new XmlParseException('Unable to parse response as XML: ' . $body);
        }

        return $parsed;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */

    /**
     * @param mixed $payload
     *
     * @return false|string
     */
    public function serialize($payload)
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        list($_, $dom) = $this->_future_serializeAsXml($payload);

        /* @var \DOMDocument $dom */

        return $dom->saveXML();
    }

    /**
     * @param mixed $payload
     *
     * @return string
     */
    public function serialize_clean($payload): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $this->serialize_node($xml, $payload);

        return $xml->outputMemory(true);
    }

    /**
     * @param \XMLWriter $xmlw
     * @param mixed      $node to serialize
     *
     * @return void
     */
    public function serialize_node(&$xmlw, $node)
    {
        if (!\is_array($node)) {
            $xmlw->text($node);
        } else {
            foreach ($node as $k => $v) {
                $xmlw->startElement($k);
                $this->serialize_node($xmlw, $v);
                $xmlw->endElement();
            }
        }
    }

    /**
     * @param mixed        $value
     * @param \DOMElement  $parent
     * @param \DOMDocument $dom
     *
     * @return array{0:\DOMElement,1:\DOMDocument}
     */
    private function _future_serializeArrayAsXml(&$value, \DOMElement $parent, \DOMDocument $dom): array
    {
        foreach ($value as $k => &$v) {
            $n = $k;
            if (\is_numeric($k)) {
                $n = "child-{$n}";
            }

            $el = $this->_future_createElement($dom, (string) $n);
            $parent->appendChild($el);
            $this->_future_serializeAsXml($v, $el, $dom);
        }

        return [$parent, $dom];
    }

    /**
     * @param mixed             $value
     * @param \DOMNode|null     $node
     * @param \DOMDocument|null $dom
     *
     * @return array{0:\DOMNode,1:\DOMDocument}
     */
    private function _future_serializeAsXml(
        &$value,
        ?\DOMNode $node = null,
        ?\DOMDocument $dom = null
    ): array {
        if (!$dom) {
            $dom = new \DOMDocument();
        }

        if (!$node) {
            if (!\is_object($value)) {
                $node = $this->_future_createElement($dom, 'response');
                $dom->appendChild($node);
            } else {
                $node = $dom; // is it correct, that we use the "dom" as "node"?
            }
        }

        if (\is_object($value)) {
            $objNode = $this->_future_createElement($dom, \get_class($value));
            $node->appendChild($objNode);
            $this->_future_serializeObjectAsXml($value, $objNode, $dom);
        } elseif (\is_array($value)) {
            $arrNode = $this->_future_createElement($dom, 'array');
            $node->appendChild($arrNode);
            $this->_future_serializeArrayAsXml($value, $arrNode, $dom);
        } elseif ((bool) $value === $value) {
            $node->appendChild($dom->createTextNode($value ? 'TRUE' : 'FALSE'));
        } else {
            $node->appendChild($dom->createTextNode($value));
        }

        return [$node, $dom];
    }

    /**
     * @throws \RuntimeException
     */
    private function _future_createElement(\DOMDocument $dom, string $name): \DOMElement
    {
        $node = $dom->createElement($name);

        if ($node === false) {
            throw new \RuntimeException('Unable to create DOM element: ' . $name);
        }

        return $node;
    }

    /**
     * @param mixed        $value
     * @param \DOMElement  $parent
     * @param \DOMDocument $dom
     *
     * @return array{0:\DOMElement,1:\DOMDocument}
     */
    private function _future_serializeObjectAsXml(&$value, \DOMElement $parent, \DOMDocument $dom): array
    {
        $refl = new \ReflectionObject($value);
        foreach ($refl->getProperties() as $pr) {
            if (!$pr->isPrivate()) {
                $el = $this->_future_createElement($dom, $pr->getName());
                $parent->appendChild($el);
                $value = $pr->getValue($value);
                $this->_future_serializeAsXml($value, $el, $dom);
            }
        }

        return [$parent, $dom];
    }
}
