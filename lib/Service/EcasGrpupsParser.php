<?php
namespace OCA\UserCAS\Service;
  /**
   * Parser for phpCAS with attribute parse callback
   */
  use phpCAS;
  class EcasGrpupsParser
  {
    /**
     * Class EcasGrpupsParser
     * @param \DOMElement $root XML element coming from phpCAS
     * @return array|string Attributes
     * @see \phpCAS
     */
    public function parse(\DOMElement $root): array|string {
      phpCAS::trace('Found attribute '.$root->nodeName);
      $result = array();

      if ($root->hasAttributes()) {
        $attrs = $root->attributes;
        foreach ($attrs as $attr) {
          if ($attr->name === 'number') {
            continue;
          }
          phpCAS::trace(
            'Additional attribute '.$attr->name.' : '.$attr->value
          );
          $result['@attributes'][$attr->name] = $attr->value;
        }
      }

      if ($root->hasChildNodes()) {
        $children = $root->childNodes;
        if ($children->length === 1) {
          $child = $children->item(0);
          if ($child->nodeType === XML_TEXT_NODE) {
            $result['_value'] = $child->nodeValue;
            return count($result) === 1
              ? $result['_value']
              : $result;
          }
        }
        $groups = array();
        foreach ($children as $child) {
          $nodeName = str_replace('cas:', '', $child->nodeName);
          phpCAS::traceBegin();
          if ($nodeName === 'groups' ) {
            $result['groups'] = array();
            phpCas::traceBegin();
            foreach ($child->childNodes as $groupChild) {
              $result['groups'][]
                = $this->parse($groupChild);
            }
            phpCAS::traceEnd('Parsed groups');
          } elseif (!isset($result[$nodeName])) {
            $result[$nodeName] = $this->parse($child);
          } else {
            if (!isset($groups[$nodeName])) {
              $result[$nodeName] = array($result[$nodeName]);
              $groups[$nodeName] = 1;
            }
            $result[$nodeName][] = $this->parse($child);
          }
          phpCAS::traceEnd();

        }
      }
      return $result;
    }
  }

