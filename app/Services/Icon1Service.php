<?php declare(strict_types=1);

namespace App\Services;

use phpDocumentor\Reflection\PseudoTypes\NumericString;

class Icon1Service {
    protected array $providers = [];
    protected array $providers_data = [];

    public function __construct(array $providers) {
        foreach($providers as $provider) {
            if(!isset($provider['id'])) {
                error_log('Icon1 provider `ID` is missing');
                continue;
            }
            if(!isset($provider['name'])) {
                error_log('Icon1 provider `name` is missing');
                continue;
            }
            if(!isset($provider['list'])) {
                error_log('Icon1 provider `list` is missing');
                continue;
            }
            $this->providers[$provider['id']] = $provider;
        }
    }

    public function searchInList(string $provider, int $page, int $per_page, ?string $search = null): array {
        if(!isset($this->providers[$provider])) {
            throw new \RuntimeException('provider `' . $provider . '` does not exist');
        }
        $list = $this->read($provider, $this->providers[$provider]['list']);
        $offset = ($page - 1) * $per_page;

        if($search !== null && $search !== '') {
            $matches = [];
            $mc = 0;
            $search = strtolower($search);
            foreach($list as $icon) {
                $matched = false;
                $id = strtolower($icon->id);
                $title = strtolower($icon->title);
                if($id === $search || $title === $search) {
                    $matched = true;
                } else if(
                    strpos($title, $search) === 0 ||
                    strpos($id, $search) === 0
                ) {
                    $matched = true;
                }

                if($matched) {
                    $matches[] = $icon;
                    $mc++;
                    /*if($mc > $offset && $mc === ($per_page + $offset)) {
                        // stop the searching as soon as enough are found for the current page
                        break;
                    }*/
                }
            }

            $list = $matches;
        }

        return [
            'total' => count($list),
            'list' => array_slice($list, $offset, $per_page),
        ];
    }

    public function getProvider(): array {
        return array_map(static fn($p) => [
            'id' => $p['id'],
            'name' => $p['name'],
        ], $this->providers);
    }

    /**
     * @throws \JsonException
     */
    protected function read(string $provider, string $path) {
        if(!isset($this->providers_data[$provider])) {
            $file = file_get_contents($path);
            if(!$file) {
                throw new \RuntimeException('provider `' . $provider . '` data file not found');
            }
            $provider_data = json_decode($file, false, 512, JSON_THROW_ON_ERROR);
            $this->providers_data[$provider] = $provider_data;
        }
        return $this->providers_data[$provider];
    }
}
