<?php

namespace App\Mcp\Knowledge;

class EndpointDiscovery
{
    public function rank(array $requests, string $query, int $limit = 10): array
    {
        $terms = $this->terms($query);
        $ranked = [];

        foreach ($requests as $request) {
            $name = mb_strtolower((string) ($request['name'] ?? ''));
            $documentation = mb_strtolower(strip_tags((string) ($request['documentation'] ?? '')));
            $method = mb_strtolower((string) ($request['method'] ?? ''));
            $url = mb_strtolower((string) ($request['url'] ?? ''));
            $collection = mb_strtolower((string) ($request['collection_name'] ?? ''));
            $matched = [];
            $score = 0;

            foreach ($terms as $term) {
                $termScore = 0;
                if (str_contains($name, $term)) {
                    $termScore += 8;
                }
                if (str_contains($collection, $term)) {
                    $termScore += 5;
                }
                if (str_contains($documentation, $term)) {
                    $termScore += 3;
                }
                if (str_contains($url, $term)) {
                    $termScore += 2;
                }
                if (str_contains($method, $term)) {
                    $termScore += 1;
                }
                if ($termScore > 0) {
                    $matched[] = $term;
                    $score += $termScore;
                }
            }

            if ($score === 0) {
                continue;
            }

            $ranked[] = [
                'request_id' => $request['id'] ?? null,
                'collection_id' => $request['collection_id'] ?? null,
                'collection_name' => $request['collection_name'] ?? null,
                'folder_id' => $request['folder_id'] ?? null,
                'name' => $request['name'] ?? null,
                'method' => $request['method'] ?? null,
                'url_template' => $request['url'] ?? null,
                'documentation' => $request['documentation'] ?? '',
                'side_effect_hint' => $this->sideEffect((string) ($request['method'] ?? '')),
                'relevance_score' => $score,
                'matched_terms' => $matched,
                'authority_warning' => 'Documentation is a discovery hint; validate the stored request before execution.',
            ];
        }

        usort($ranked, fn ($a, $b) =>
            $b['relevance_score'] <=> $a['relevance_score']
            ?: strcmp((string) $a['name'], (string) $b['name'])
        );
        return array_slice($ranked, 0, max(1, min(20, $limit)));
    }

    private function terms(string $query): array
    {
        $parts = preg_split('/[^\pL\pN_]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
        $stop = ['a', 'an', 'and', 'by', 'for', 'in', 'of', 'on', 'the', 'to', 'with'];
        return array_values(array_unique(array_filter($parts ?: [], fn ($term) =>
            mb_strlen($term) >= 2 && !in_array($term, $stop, true)
        )));
    }

    private function sideEffect(string $method): string
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true) ? 'read' : 'write';
    }
}
