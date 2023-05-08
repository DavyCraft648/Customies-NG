<?php
declare(strict_types=1);

namespace customiesdevs\customies\task;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\util\Cache;
use pocketmine\block\Block;
use pocketmine\scheduler\AsyncTask;
use pocketmine\thread\NonThreadSafeValue;

final class AsyncRegisterBlocksTask extends AsyncTask {

    /**
     * @var NonThreadSafeValue[]
     */
	private array $blockFuncs;
    /**
     * @var NonThreadSafeValue[]
     */
	private array $objectToState;
    /**
     * @var NonThreadSafeValue[]
     */
	private array $stateToObject;

	/**
	 * @param Closure[] $blockFuncs
	 * @phpstan-param array<string, Closure(int): Block> $blockFuncs
	 */
	public function __construct(private string $cachePath, array $blockFuncs) {
		$this->blockFuncs = [];
		$this->objectToState = [];
		$this->stateToObject = [];

		foreach($blockFuncs as $identifier => [$blockFunc, $objectToState, $stateToObject]){
			$this->blockFuncs[$identifier] = new NonThreadSafeValue($blockFunc);
			$this->objectToState[$identifier] = new NonThreadSafeValue($objectToState);
			$this->stateToObject[$identifier] = new NonThreadSafeValue($stateToObject);
		}
	}

	public function onRun(): void {
		Cache::setInstance(new Cache($this->cachePath));
		foreach($this->blockFuncs as $identifier => $blockFunc){
            $blockFunc = $blockFunc->deserialize();
			// We do not care about the model or creative inventory data in other threads since it is unused outside of
			// the main thread.
			CustomiesBlockFactory::getInstance()->registerBlock($blockFunc, $identifier, objectToState: $this->objectToState[$identifier], stateToObject: $this->stateToObject[$identifier]);
		}
	}
}
