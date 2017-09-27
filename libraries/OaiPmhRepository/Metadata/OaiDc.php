<?php
/**
 * @package OaiPmhRepository
 * @subpackage MetadataFormats
 * @copyright Copyright 2009-2014 John Flatness, Yu-Hsun Lin
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Class implmenting metadata output for the required oai_dc metadata format.
 * oai_dc is output of the 15 unqualified Dublin Core fields.
 *
 * @package OaiPmhRepository
 * @subpackage Metadata Formats
 */
class OaiPmhRepository_Metadata_OaiDc implements OaiPmhRepository_Metadata_FormatInterface
{
    /** OAI-PMH metadata prefix */
    const METADATA_PREFIX = 'oai_dc';

    /** XML namespace for output format */
    const METADATA_NAMESPACE = 'http://www.openarchives.org/OAI/2.0/oai_dc/';

    /** XML schema for output format */
    const METADATA_SCHEMA = 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd';

    /** XML namespace for unqualified Dublin Core */
    const DC_NAMESPACE_URI = 'http://purl.org/dc/elements/1.1/';

    /** XML namespace for DC terms */
    const DCTERMS_NAMESPACE_URI = 'http://purl.org/dc/terms/';

    /**
     * Appends Dublin Core metadata.
     *
     * Appends a metadata element, an child element with the required format,
     * and further children for each of the Dublin Core fields present in the
     * item.
     */
    public function appendMetadata($item, $metadataElement)
    {
        $document = $metadataElement->ownerDocument;
        $oai_dc = $document->createElementNS(self::METADATA_NAMESPACE, 'oai_dc:dc');
        $metadataElement->appendChild($oai_dc);

        $oai_dc->setAttribute('xmlns:dc', self::DC_NAMESPACE_URI);
        $oai_dc->setAttribute('xmlns:dcterms', self::DCTERMS_NAMESPACE_URI);
        $oai_dc->declareSchemaLocation(self::METADATA_NAMESPACE, self::METADATA_SCHEMA);

        $dcElementNames = array(
            'title', 'creator', 'subject', 'description', 'publisher', 'date', 'type', 'identifier', 'rights', 'location');

        $oai_dc->appendNewElement('dc:contributor', 'Southwest Harbor Public Library');

        foreach ($dcElementNames as $elementName)
        {
            $upperName = Inflector::camelize($elementName);
            $setName = $elementName == 'location' ? 'Item Type Metadata' : 'Dublin Core';
            $dcElements = $item->getElementTexts($setName, $upperName);
            $text = empty($dcElements) ? '' : $dcElements[0]->text;

            if ($elementName == 'identifier')
                $this->appendIdentifierMetadata($oai_dc, $item);
            elseif ($elementName == 'subject')
                $this->appendSubjectMetadata($oai_dc, $dcElements);
            elseif ($elementName == 'type')
                $this->appendTypeMetadata($oai_dc, $text);
            elseif ($elementName == 'date')
                $this->appendDateMetadata($oai_dc, $text);
            elseif ($elementName == 'description')
                $this->appendDescriptionMetadata($oai_dc, $text);
            elseif ($elementName == 'location')
                $this->appendLocationMetadata($oai_dc, $item, $text);
            else
            {
                foreach ($dcElements as $elementText)
                {
                    $oai_dc->appendNewElement('dc:' . $elementName, $elementText->text);
                }
            }
        }
    }

    protected function appendDateMetadata($oai_dc, $text)
    {
        if (!empty($text))
            $oai_dc->appendNewElement('dcterms:created', $text);
    }

    protected function appendDescriptionMetadata($oai_dc, $text)
    {
        if (!empty($text))
            $oai_dc->appendNewElement('dcterms:abstract', $text);
    }

    protected function appendIdentifierMetadata($oai_dc, $item)
    {
        // Emit the item's URL as its identifier.
        $oai_dc->appendNewElement('dc:identifier', record_url($item, 'show', true));

        // Emit the URL of the item's thumbnail image.
        $files = $item->getFiles();
        if (count($files) >= 1)
        {
            $oai_dc->appendNewElement('dcterms:hasFormat', $files[0]->getWebPath('thumbnail'));
        }
    }

    protected function appendLocationMetadata($oai_dc, $item, $text)
    {
        $elements = $item->getElementTexts('Item Type Metadata', 'State');
        $state = count($elements) >= 1 ? $elements[0]->text : '';
        $elements = $item->getElementTexts('Item Type Metadata', 'Country');
        $country = count($elements) >= 1 ? $elements[0]->text : '';

        $parts = explode(',', $text);
        $parts = array_map('trim', $parts);

        foreach ($parts as $part)
        {
            $location = $part;
            if ($location == 'MDI')
            {
                $location = 'Mount Desert Island';
            }
            if (!empty($state))
            {
                if ($state == 'ME')
                    $state = 'Maine';
                if (!empty($location))
                    $location .= ', ';
                $location .= $state;
            }
            if (!empty($country) && $country != 'USA')
            {
                if (!empty($location))
                    $location .= ', ';
                $location .= $country;
            }

            $oai_dc->appendNewElement('dcterms:spatial', $location);
        }
    }

    protected function appendSubjectMetadata($oai_dc, $dcElements)
    {
        // Create an array of unique subject values from the item's subject element(s).
        // This way, if the item has two subjects e.g. 'Places, Town' and 'Places, Shore',
        // three dc:subject elements will get emitted: 'Places', 'Town', and 'Shore'.
        $subjects = array();

        foreach ($dcElements as $elementText)
        {
            $parts = explode(',', $elementText->text);
            $parts = array_map('trim', $parts);
            foreach ($parts as $part)
            {
                $subjects[] = $part;
            }
        }

        $subjects = array_unique($subjects);
        foreach ($subjects as $subject)
        {
            if ($subject == 'Other')
                continue;
            $oai_dc->appendNewElement('dc:subject', $subject);
        }
    }

    protected function appendTypeMetadata($oai_dc, $text)
    {
        $parts = explode(',', $text);
        $parts = array_map('trim', $parts);

        foreach ($parts as $index => $part)
        {
            if ($index == 0)
            {
                $type = $parts[0];
                if ($type == 'Article' || $type == 'Document' || $type == 'Publication')
                {
                    $oai_dc->appendNewElement('dc:type', 'Text');
                    if ($type == 'Article')
                        break;
                }
                elseif ($type == 'Map')
                {
                    $oai_dc->appendNewElement('dc:type', 'Image');
                    $oai_dc->appendNewElement('dc:format', 'Map');
                    break;
                }
                else
                {
                    $oai_dc->appendNewElement('dc:type', $type);
                }
            }
            else
            {
                $oai_dc->appendNewElement('dc:format', $part);
            }
        }
    }
}
