<?php

namespace App\Services;

class LegalDocumentService
{
    public function document(string $type): array
    {
        $document = config('legal.documents.'.$type);
        abort_unless(is_array($document), 404);
        if (config('payments.provider') === 'corefy') {
            foreach (config('corefy_legal.'.$type, []) as $sectionIndex => $paragraphs) {
                $document['sections'][$sectionIndex]['paragraphs'] = $paragraphs;
            }
        }
        $replacements = [];
        foreach (config('company') as $key => $value) {
            $replacements['{company.'.$key.'}'] = (string) $value;
        }
        foreach ($document['sections'] as &$section) {
            $section['paragraphs'] = array_map(fn (string $text): string => strtr($text, $replacements), $section['paragraphs']);
        }
        unset($section);

        return [...$document, 'version' => config('legal.version')];
    }
}
