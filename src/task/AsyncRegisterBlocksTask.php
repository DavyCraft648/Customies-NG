<?php
declare(strict_types=1);

namespace customiesdevs\customies\task;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\util\Cache;
use pocketmine\block\Block;
use pocketmine\data\runtime\RuntimeEnumDeserializerTrait;
use pocketmine\scheduler\AsyncTask;
use pocketmine\thread\NonThreadSafeValue;

final class AsyncRegisterBlocksTask extends AsyncTask
{

    private NonThreadSafeValue $block;

    /**
     * @param Closure[] $blockFuncs
     * @phpstan-param array<string, Closure(int): Block> $blockFuncs
     */
    public function __construct(private string $cachePath, array $blockFuncs)
    {
        $this->block = new NonThreadSafeValue($blockFuncs);
	}

    public function onRun(): void
    {
        Cache::setInstance(new Cache($this->cachePath));
        foreach ($this->block->deserialize() as $identifier => [$blockFunc, $objectToState, $stateToObject]) {
            // We do not care about the model or creative inventory data in other threads since it is unused outside of
            // the main thread.
            CustomiesBlockFactory::getInstance()->registerBlock($blockFunc, $identifier, objectToState: $objectToState, stateToObject: $stateToObject);
        }
    }
}
