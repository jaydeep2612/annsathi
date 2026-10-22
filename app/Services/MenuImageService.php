<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuImageService
{
    /**
     * Get all unique global images from storage/app/public/menu_images
     *
     * @return array<string, string> filename => Display Name
     */
    public static function getGlobalImages(): array
    {
        $disk = Storage::disk('public');
        if (!$disk->exists('menu_images')) {
            return [];
        }

        $files = $disk->files('menu_images');
        $options = [];

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                $base = pathinfo($filename, PATHINFO_FILENAME);
                $friendlyName = Str::headline($base);
                $options['menu_images/' . $filename] = $friendlyName . ' (' . $filename . ')';
            }
        }

        asort($options);
        return $options;
    }

    /**
     * Intelligently find the single best matching global image for an item name.
     * Used during CSV import and automatic initial item creation.
     */
    public static function findBestMatch(string $itemName, ?string $categoryName = null): ?string
    {
        $matches = self::getMatchingSuggestions($itemName, limit: 1);
        if (!empty($matches) && ($matches[0]['score'] ?? 0) >= 30) {
            return $matches[0]['path'];
        }

        // Try category name if item name had no match
        if ($categoryName) {
            $catMatches = self::getMatchingSuggestions($categoryName, limit: 1);
            if (!empty($catMatches) && ($catMatches[0]['score'] ?? 0) >= 40) {
                return $catMatches[0]['path'];
            }
        }

        return null;
    }

    /**
     * Common culinary category and item aliases for global images.
     * Enables generic search terms (e.g. "soup", "roti", "biryani", "ice cream", "cold drink")
     * to accurately surface all relevant dishes.
     */
    protected static array $IMAGE_ALIASES = [
        // Soups
        'cream-of-tomato.jpg'       => ['soup', 'tomato soup', 'cream of tomato soup', 'cream soup', 'tameta soup'],
        'hot-sour.jpg'              => ['soup', 'hot and sour soup', 'hot sour soup', 'hot & sour soup', 'chinese soup'],
        'manchow.jpg'               => ['soup', 'manchow soup', 'veg manchow soup', 'chinese soup'],
        'lemon-coriendor.jpg'       => ['soup', 'lemon coriander soup', 'lemon soup', 'coriander soup'],
        'veg-sweet-corn.jpg'        => ['soup', 'sweet corn soup', 'sweet corn', 'corn soup', 'veg sweet corn'],

        // Breads & Roti
        'butter-naan.jpg'           => ['naan', 'butter naan', 'roti', 'bread', 'tandoori naan', 'tandoori roti'],
        'butter-roti.jpg'           => ['roti', 'butter roti', 'tandoori roti', 'chapati', 'phulka', 'bread'],
        'butter-kulcha.jpg'         => ['kulcha', 'butter kulcha', 'bread', 'naan', 'roti'],
        'chapati.jpg'               => ['chapati', 'roti', 'phulka', 'bread', 'plain roti'],
        'paratha.jpg'               => ['paratha', 'butter paratha', 'parotta', 'bread', 'roti'],

        // Rice / Biryani / Pulav
        'biryani.jpg'               => ['biryani', 'dum biryani', 'veg biryani', 'rice'],
        'handi-dum-biryani.jpg'     => ['biryani', 'dum biryani', 'handi biryani', 'veg biryani', 'rice'],
        'veg-hydrabadi-biryani.jpg' => ['biryani', 'hyderabadi biryani', 'veg biryani', 'rice'],
        'jeera-rice.jpg'            => ['jeera rice', 'rice', 'cumin rice'],
        'peas-pulav.jpg'            => ['pulav', 'pulao', 'peas pulav', 'matar pulav', 'rice'],
        'veg-pulav.jpg'             => ['pulav', 'pulao', 'veg pulav', 'vegetable pulav', 'rice'],

        // Dal
        'dal-fry.jpg'               => ['dal', 'dal fry', 'daal', 'yellow dal', 'curry'],
        'dal-palak.jpg'             => ['dal', 'dal palak', 'palak dal', 'daal', 'spinach dal'],
        'tadka.jpg'                 => ['dal', 'dal tadka', 'tadka dal', 'daal', 'curry'],

        // Sabji / Curries / Paneer
        'paneer-butter-masala-red.jpg' => ['paneer butter masala', 'butter paneer', 'paneer makhani', 'paneer'],
        'paneer-tikka-masala-red.jpg'  => ['paneer tikka', 'paneer tikka masala', 'tikka masala', 'paneer'],
        'paneer-kadai-brown.jpg'    => ['kadai paneer', 'paneer kadai', 'kadhai paneer', 'paneer'],
        'paneer-handi.jpg'          => ['paneer handi', 'handi paneer', 'paneer'],
        'paneer-toofani-red.jpg'    => ['paneer toofani', 'toofani paneer', 'paneer'],
        'paneer-tawa-masala-red.jpg'=> ['paneer tawa masala', 'tawa paneer', 'paneer'],
        'paneer-kofta-red.jpg'      => ['paneer kofta', 'kofta', 'paneer'],
        'paneer-chatpata-brown.jpg' => ['paneer chatpata', 'chatpata paneer', 'paneer'],
        'kadai-brown.jpg'           => ['kadai veg', 'veg kadai', 'kadai'],
        'balti-red.jpg'             => ['veg balti', 'balti', 'paneer balti'],
        'kasmiri-kofta-white.jpg'   => ['kashmiri kofta', 'kofta', 'white gravy'],
        'nargisi-kofta-green.jpg'   => ['nargisi kofta', 'kofta', 'green gravy', 'palak kofta'],
        'veg-kofta-brown.jpg'       => ['veg kofta', 'kofta'],
        'veg-kolhapuri-red.jpg'     => ['veg kolhapuri', 'kolhapuri', 'spicy veg'],
        'veg-makhanwala-red.jpg'    => ['veg makhanwala', 'makhanwala', 'makhani'],
        'veg-toofani-red.jpg'       => ['veg toofani', 'toofani'],
        'veg-jaipuri-brown.jpg'     => ['veg jaipuri', 'jaipuri'],
        'veg-mughlai-brown.jpg'     => ['veg mughlai', 'mughlai'],
        'veg-handi.jpg'             => ['veg handi', 'handi veg'],
        'veg-hydrabadi-green.jpg'   => ['veg hyderabadi', 'hyderabadi veg', 'green gravy'],
        'chana-masala.jpg'          => ['chana masala', 'chole', 'chana', 'chickpeas'],
        'bhindi-masala.jpg'         => ['bhindi masala', 'bhindi', 'okra', 'ladyfinger'],
        'mug-masala.jpg'            => ['moong masala', 'mug masala', 'green gram'],
        'sev-tamate.jpg'            => ['sev tameta', 'sev tomato', 'sev tamatar', 'kathiyawadi'],
        'lusaniya-bataka.jpg'       => ['lasaniya batata', 'garlic potato', 'bataka', 'kathiyawadi'],
        'rusawala-bataka-tomato.jpg'=> ['bataka tameta', 'potato tomato', 'rasawala bataka'],

        // Starters / Snacks / Fast Food
        'custom-burger.jpg'         => ['burger', 'veg burger', 'cheese burger', 'fast food'],
        'dragon-sushi-roll.jpg'     => ['sushi', 'sushi roll', 'japanese'],
        'veg-manchurian.jpg'        => ['manchurian', 'veg manchurian', 'chinese', 'starter'],
        'panner-manchurian.jpg'     => ['paneer manchurian', 'manchurian', 'chinese', 'starter'],
        'paneer-chilli-dry.jpg'     => ['chilli paneer', 'paneer chilli', 'chilli paneer dry', 'chinese', 'starter'],
        'paneer-65.jpg'             => ['paneer 65', 'starter', 'appetizer'],
        'veg-spring-roll.jpg'       => ['spring roll', 'veg spring roll', 'chinese', 'starter', 'roll'],
        'veg-lollipop.jpg'          => ['veg lollipop', 'lollipop', 'starter', 'chinese'],
        'veg-hara-bhara-kabab.jpg'  => ['hara bhara kabab', 'kabab', 'kebab', 'veg kabab', 'starter'],
        'samosa.jpg'                => ['samosa', 'snack', 'starter', 'nasta'],
        'corn-tikki.jpg'            => ['corn tikki', 'tikki', 'starter', 'snack'],
        'cutlet.jpg'                => ['cutlet', 'veg cutlet', 'starter', 'snack'],
        'mix-pakoda.jpg'            => ['pakoda', 'bhajiya', 'pakora', 'fritters', 'snack', 'bhajia'],

        // Chaat & Street Food
        'pani-puri.jpg'             => ['pani puri', 'golgappa', 'puchka', 'chaat', 'chat', 'street food'],
        'sev-puri.jpg'              => ['sev puri', 'chaat', 'chat', 'street food'],
        'aloo-tikki-chat.jpg'       => ['aloo tikki chat', 'aloo tikki', 'tikki chaat', 'chaat', 'chat'],
        'basket-chat.jpg'           => ['basket chat', 'katori chat', 'chaat', 'chat'],
        'papdi-chat.jpg'            => ['papdi chat', 'papdi chaat', 'chaat', 'chat'],
        'khaman.jpg'                => ['khaman', 'dhokla', 'gujarati', 'farsan', 'snack'],
        'khandvi.jpg'               => ['khandvi', 'gujarati', 'farsan', 'snack'],
        'patra.jpg'                 => ['patra', 'gujarati', 'farsan', 'snack'],
        'lilva-kachori.jpg'         => ['kachori', 'lilva kachori', 'farsan', 'snack'],

        // Papads
        'fry-papad.jpg'             => ['papad', 'fry papad', 'fried papad'],
        'roasted-papad.jpg'         => ['papad', 'roast papad', 'roasted papad'],
        'pum-pum-papad.jpg'         => ['papad', 'masala papad', 'pum pum papad'],

        // Beverages
        'pepsi.jpg'                 => ['pepsi', 'cold drink', 'soft drink', 'soda', 'beverage', 'drink', 'cola'],
        'sprite.jpg'                => ['sprite', 'cold drink', 'soft drink', 'soda', 'beverage', 'drink'],
        'fanta.jpg'                 => ['fanta', 'cold drink', 'soft drink', 'soda', 'beverage', 'drink', 'orange soda'],
        'mint-mojito.jpg'           => ['mojito', 'mint mojito', 'mocktail', 'beverage', 'drink', 'coolers'],
        'pina-colada.jpg'           => ['pina colada', 'mocktail', 'beverage', 'drink', 'shake'],
        'fruit-punch.jpg'           => ['fruit punch', 'mocktail', 'juice', 'beverage', 'drink'],
        'orange-blossom.jpg'        => ['orange blossom', 'mocktail', 'juice', 'beverage', 'drink'],

        // Desserts / Sweets / Ice Creams
        'vanilla.jpg'               => ['vanilla', 'vanilla ice cream', 'ice cream', 'icecream', 'dessert', 'sweet'],
        'chocolate.jpg'             => ['chocolate', 'chocolate ice cream', 'ice cream', 'icecream', 'dessert', 'sweet'],
        'butterscotch.jpg'          => ['butterscotch', 'butterscotch ice cream', 'ice cream', 'icecream', 'dessert', 'sweet'],
        'strawberry.jpg'            => ['strawberry', 'strawberry ice cream', 'ice cream', 'icecream', 'dessert', 'sweet'],
        'kaju-draksh.jpg'           => ['kaju draksh', 'kaju draksh ice cream', 'ice cream', 'icecream', 'dessert'],
        'american-nuts.jpg'         => ['american nuts', 'american nuts ice cream', 'ice cream', 'icecream', 'dessert'],
        'kesar-pista.jpg'           => ['kesar pista', 'kesar pista ice cream', 'ice cream', 'icecream', 'dessert'],
        'malai.jpg'                 => ['malai', 'malai kulfi', 'kulfi', 'ice cream', 'dessert'],
        'vanilla-with-hot-chocolate.jpg' => ['sundae', 'hot chocolate fudge', 'vanilla with hot chocolate', 'ice cream', 'dessert'],
        'gulab-jamun.jpg'           => ['gulab jamun', 'sweet', 'mithai', 'dessert'],
        'kala-jamun.jpg'            => ['kala jamun', 'sweet', 'mithai', 'dessert'],
        'rajbhog.jpg'               => ['rajbhog', 'sweet', 'mithai', 'rasgulla', 'dessert'],
        'gajar-halwa-seasonal.jpg'  => ['gajar halwa', 'carrot halwa', 'halwa', 'sweet', 'dessert'],
        'moong-dal-halwa.jpg'       => ['moong dal halwa', 'halwa', 'sweet', 'dessert'],
        'dudhi-halwa.jpg'           => ['dudhi halwa', 'lauki halwa', 'halwa', 'sweet', 'dessert'],
        'srikhand.jpg'              => ['shrikhand', 'srikhand', 'sweet', 'mithai', 'dessert'],
        'mango-ras-seasonal.jpg'    => ['aamras', 'mango ras', 'aam ras', 'sweet', 'mango'],
        'mango-delight-seasonal.jpg'=> ['mango delight', 'mango', 'ice cream', 'dessert', 'sweet'],
        'angoori-basundi.jpg'       => ['basundi', 'angoori basundi', 'sweet', 'dessert'],
        'anjeer-basundi.jpg'        => ['basundi', 'anjeer basundi', 'sweet', 'dessert'],
        'kesar-pista-basundi.jpg'   => ['basundi', 'kesar pista basundi', 'sweet', 'dessert'],
        'sitafal-basundi.jpg'       => ['basundi', 'sitafal basundi', 'sweet', 'dessert'],
    ];

    /**
     * Get visual matching image suggestions based on item name and optional category.
     * Returns list of arrays: ['filename' => ..., 'path' => ..., 'url' => ..., 'label' => ..., 'score' => ...]
     */
    public static function getMatchingSuggestions(string $itemName, int $limit = 6): array
    {
        $cleanItem = strtolower(trim($itemName));
        if (strlen($cleanItem) < 2) {
            return []; // Do not display suggestions before user writes the item name
        }

        $disk = Storage::disk('public');
        if (!$disk->exists('menu_images')) {
            return [];
        }

        $files = $disk->files('menu_images');
        if (empty($files)) {
            return [];
        }

        // Common stopwords & grammatical filler words to exclude from keyword matching
        $stopWords = [
            'of', 'in', 'on', 'at', 'to', 'for', 'with', 'without', 'and', 'or', 'the', 'a', 'an',
            'by', 'from', 'as', 'is', 'ka', 'ki', 'ke', 'wali', 'wala', 'small', 'medium', 'large'
        ];

        // Tokenize search query (remove units like 1L, 500ml, 50g, and non-alphanumeric chars)
        $cleanTokens = preg_replace('/(\d+)\s*(ml|l|g|kg|pcs|pc)/i', '', $cleanItem);
        $cleanTokens = preg_replace('/[^a-z0-9\s]/', ' ', $cleanTokens);
        $rawTokens = array_filter(explode(' ', $cleanTokens), fn($t) => strlen($t) >= 2);
        
        // Filter out stopwords
        $tokens = array_values(array_filter($rawTokens, fn($t) => !in_array($t, $stopWords) && strlen($t) >= 2));
        $itemSlug = Str::slug($cleanItem);

        $scored = [];

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                continue;
            }

            $base = strtolower(pathinfo($filename, PATHINFO_FILENAME));
            $baseSlug = Str::slug($base);
            $baseWords = explode('-', $baseSlug);

            $score = 0;

            // 1. Exact match on slug
            if ($baseSlug === $itemSlug) {
                $score += 120;
            }
            // 2. Contains entire base slug or base contains entire item slug
            elseif (str_contains($itemSlug, $baseSlug) && strlen($baseSlug) >= 4) {
                $score += 70;
            } elseif (str_contains($baseSlug, $itemSlug) && strlen($itemSlug) >= 4) {
                $score += 65;
            }

            // 3. Keyword intersection scoring with base filename words
            $matchedTokensCount = 0;
            foreach ($tokens as $token) {
                $tokenMatched = false;
                foreach ($baseWords as $bWord) {
                    if ($bWord === $token) {
                        $score += 35;
                        $tokenMatched = true;
                        break;
                    } elseif (Str::plural($token) === $bWord || Str::singular($token) === $bWord) {
                        $score += 30;
                        $tokenMatched = true;
                        break;
                    } elseif (strlen($token) >= 5 && strlen($bWord) >= 5 && (Str::startsWith($bWord, $token) || Str::startsWith($token, $bWord))) {
                        $score += 20;
                        $tokenMatched = true;
                        break;
                    }
                }
                if ($tokenMatched) {
                    $matchedTokensCount++;
                }
            }

            // 4. Culinary Aliases & Category Matching
            if (isset(self::$IMAGE_ALIASES[$filename])) {
                foreach (self::$IMAGE_ALIASES[$filename] as $alias) {
                    $aliasSlug = Str::slug($alias);
                    if ($aliasSlug === $itemSlug || $alias === $cleanItem) {
                        $score += 100;
                        $matchedTokensCount = count($tokens);
                        break;
                    } elseif (str_contains($aliasSlug, $itemSlug) || str_contains($itemSlug, $aliasSlug)) {
                        $score += 60;
                        $matchedTokensCount = max($matchedTokensCount, 1);
                    } else {
                        $aliasWords = explode('-', $aliasSlug);
                        foreach ($tokens as $token) {
                            if (in_array($token, $aliasWords)) {
                                $score += 25;
                                $matchedTokensCount = max($matchedTokensCount, 1);
                            }
                        }
                    }
                }
            }

            // Multi-keyword combination bonus: If multiple search tokens were provided and all of them matched!
            if (count($tokens) > 1 && $matchedTokensCount === count($tokens)) {
                $score += 50;
            }

            // Only consider meaningful positive scores
            if ($score >= 30) {
                $scored[] = [
                    'filename' => $filename,
                    'path'     => 'menu_images/' . $filename,
                    'url'      => $disk->url('menu_images/' . $filename),
                    'label'    => Str::headline($base),
                    'score'    => $score,
                ];
            }
        }

        // Sort descending by score
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        if (empty($scored)) {
            return [];
        }

        $topScore = $scored[0]['score'];

        // Strict relevance filtering:
        // If we have a very strong / exact match (score >= 90), prune low-relevance partial noise.
        // Suggestions must have at least 50% of the top candidate's score.
        if ($topScore >= 90) {
            $scored = array_filter($scored, fn($s) => $s['score'] >= ($topScore * 0.5) && $s['score'] >= 50);
        } else {
            // For general partial matches, require at least 30 score and at least 40% of top score
            $scored = array_filter($scored, fn($s) => $s['score'] >= ($topScore * 0.4) && $s['score'] >= 30);
        }

        return array_slice(array_values($scored), 0, $limit);
    }

    /**
     * Automatically contributes a unique custom uploaded image into the global shared library.
     * Named after the item name slug. Prevents duplicates by checking content MD5 hash.
     *
     * @param string $customRelativePath (e.g. 'restaurants/slug/Categories/cat/item.jpg' on public disk)
     * @param string $itemName (e.g. 'Paneer Butter Masala')
     * @return string|null The global path ('menu_images/filename.ext') or null if not copied
     */
    public static function contributeToGlobalLibrary(string $customRelativePath, string $itemName): ?string
    {
        $disk = Storage::disk('public');
        if (!$disk->exists($customRelativePath)) {
            return null;
        }

        $content = $disk->get($customRelativePath);
        if (empty($content)) {
            return null;
        }

        $sourceHash = md5($content);

        if (!$disk->exists('menu_images')) {
            $disk->makeDirectory('menu_images');
        }

        // 1. Deduplication check: inspect all existing files in menu_images
        $existingFiles = $disk->files('menu_images');
        foreach ($existingFiles as $file) {
            if (md5($disk->get($file)) === $sourceHash) {
                // Identical image already exists in global library! Do not duplicate.
                return $file;
            }
        }

        // 2. Determine target filename based on item name
        $slug = Str::slug($itemName);
        if (empty($slug)) {
            $slug = 'item-' . Str::random(6);
        }

        $ext = strtolower(pathinfo($customRelativePath, PATHINFO_EXTENSION)) ?: 'jpg';
        $targetFilename = "{$slug}.{$ext}";
        $targetRelativePath = "menu_images/{$targetFilename}";

        // If target filename already exists but with different content, append unique suffix
        if ($disk->exists($targetRelativePath)) {
            $targetFilename = "{$slug}-" . substr($sourceHash, 0, 6) . ".{$ext}";
            $targetRelativePath = "menu_images/{$targetFilename}";
        }

        // 3. Save copy to global images
        $disk->put($targetRelativePath, $content);

        return $targetRelativePath;
    }

    /**
     * Determine custom upload directory matching:
     * restaurants/{restaurant_slug}/Categories/{category_slug}
     */
    public static function getCustomCategoryUploadPath(?string $restaurantSlug, ?string $categoryName): string
    {
        $rSlug = $restaurantSlug ?: 'default';
        $cSlug = $categoryName ? Str::slug($categoryName) : 'general';
        return "restaurants/{$rSlug}/Categories/{$cSlug}";
    }
}
