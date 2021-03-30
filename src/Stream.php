<?php

namespace EBANX\Stream;

class Stream implements \Iterator, \Countable {

	/** @var \Iterator */
	private $source_iterator;

	protected function __construct(\Iterator $source_iterator) {
		$this->source_iterator = $source_iterator;
	}

	public function current() {
		return $this->source_iterator->current();
	}

	public function next() {
		$this->source_iterator->next();
	}

	public function key() {
		return $this->source_iterator->key();
	}

	public function valid() {
		return $this->source_iterator->valid();
	}

	public function rewind() {
		$this->source_iterator->rewind();
	}

	/**
	 * Create a new stream from the given iterable (any primitive array or \Traversable).
	 *
	 * Stream
	 *
	 * @param iterable $iterable
	 * @return static
	 */
	public static function of(iterable $iterable): self {
		$generator = function () use ($iterable) {
			foreach ($iterable as $item) {
				yield $item;
			}
		};
		return new self($generator());
	}


	/**
	 * Create a new stream from the given iterable (any primitive array or \Traversable).
	 * as [key, value] format
	 *
	 * @param iterable $iterable
	 * @return static
	 */
	public static function ofKeyValueMap(iterable $iterable): self {
		$generator = function () use ($iterable) {
			foreach ($iterable as $key => $item) {
				yield [$key, $item];
			}
		};
		return new self($generator());
	}

	/**
	 * Creates a stream with int numbers from start to end by increments of a step.
	 * The range is inclusively.
	 *
	 * @param int $start
	 * @param int $end
	 * @param int $step
	 * @return static
	 */
	public static function rangeInt(int $start, int $end, int $step = 1): self {
		return self::range($start, $end, $step);
	}

	/**
	 * Creates a stream with float numbers from start to end by increments of a step.
	 * The range is inclusively.
	 *
	 * @param float $start
	 * @param float $end
	 * @param float $step
	 * @return static
	 */
	public static function rangeFloat(float $start, float $end, float $step = 1): self {
		return self::range($start, $end, $step);
	}

	/**
	 * Collects all stream elements into an array.
	 *
	 * @return array
	 */
	public function collect(): array {
		return iterator_to_array($this, $use_keys=false);
	}

	/**
	 * Collects all stream elements into an associative array.
	 * Each element needs to be an array tuple where the first element will be the key
	 * and the second one will be the value.
	 *
	 * @return array
	 */
	public function collectAsKeyValue(): array {
		$result = [];
		foreach ($this as [$key, $value]) {
			$result[$key] = $value;
		}
		return $result;
	}

	/**
	 * @return mixed
	 */
	public function collectFirst() {
		$collected = $this->take(1)->collect();
		if (empty($collected)) {
			throw new \InvalidArgumentException('No elements available in this stream.');
		}
		return array_shift($collected);
	}

	/**
	 * Transform the stream by applying the callback to each element of the stream.
	 *
	 * @param callable $callback
	 * @return $this
	 */
	public function map(callable $callback): self {
		$generator = function (Stream $stream) use ($callback) {
			foreach ($stream as $value) {
				yield $callback($value);
			}
		};
		return new self($generator($this));
	}

	/**
	 * Maps the stream using the callback and flattens the result.
	 *
	 * @param callable $callback
	 * @return $this
	 */
	public function flatMap(callable $callback): self {
		$generator = function (Stream $stream) use ($callback) {
			foreach ($stream->map($callback) as $traversable) {
				foreach ($traversable as $value) {
					yield $value;
				}
			}
		};
		return new self($generator($this));
	}

	/**
	 * Filters the stream keeping only elements for which the callback returns true.
	 *
	 * @param callable $callback
	 * @return $this
	 */
	public function filter(callable $callback): self {
		$generator = function (Stream $stream) use ($callback) {
			foreach ($stream as $value) {
				if ($callback($value)) {
					yield $value;
				}
			}
		};
		return new self($generator($this));
	}

	/**
	 * Filter the elements from the stream until a false return, then nothing else will be filtered
	 *
	 * @param callable $callback
	 * @return $this
	 */
	public function skipWhile(callable $callback): self {
		$generator = static function(Stream $stream) use ($callback) {
			$keep_value = false;
			foreach ($stream as $value) {
				if ($keep_value || !$callback($value)) {
					yield $value;
					$keep_value = true;
				}
			}
		};

		return new self($generator($this));
	}

	/**
	 * Take n elements of the stream.
	 *
	 * @param int $n_elements
	 * @return $this
	 */
	public function take(int $n_elements): self {
		return new self(new \LimitIterator($this, 0, $n_elements));
	}

	/**
	 * Skip n elements of the stream.
	 *
	 * @param int $n_elements
	 * @return $this
	 */
	public function skip(int $n_elements): self {
		return new self(new \LimitIterator($this, $n_elements));
	}

	/**
	 * Separate the current stream into chunks of chunk size.
	 * Each chunk of elements is returned as an array.
	 *
	 * @param int $chunk_size
	 * @return $this
	 */
	public function chunkEvery(int $chunk_size): self {
		return $this->chunkBy(
			function ($_) use ($chunk_size) {
				static $current_size;
				return (int)($current_size++ / $chunk_size);
			}
		);
	}

	/**
	 * Separate the current stream into chunks limited by the custom function. The elements will be chunked while the
	 * test function returns the same value or until the stream ends. If a new value is returned from the test function
	 * this value will be added to the next chunk.
	 * Each chunk of elements is returned as an array.
	 *
	 * @param callable $chunk_test
	 * @return $this
	 */
	public function chunkBy(callable $chunk_test): self {
		$generator = function (Stream $stream) use ($chunk_test) {
			$chunk = [];
			$last_test_value = null;

			$stream->rewind();
			if ($stream->valid()) {
				$last_test_value = $chunk_test($stream->current());
				$chunk[] = $stream->current();
			}
			$stream->next();
			while ($stream->valid()) {
				$current_test_value = $chunk_test($stream->current());
				if ($last_test_value !== $current_test_value) {
					yield $chunk;
					$chunk = [];
				}
				$chunk[] = $stream->current();
				$last_test_value = $current_test_value;
				$stream->next();
			}
			if (count($chunk) > 0) {
				yield $chunk;
			}
		};
		return new self($generator($this));
	}

	/**
	 * Applies the callback to each element of the stream ignoring the return.
	 * This method consumes the stream.
	 *
	 * @param callable $callback
	 */
	public function forEach(callable $callback): void {
		foreach ($this as $value) {
			$callback($value);
		}
	}

	/**
	 * Join all elements of the stream using the given glue and returning a string.
	 * This method consumes the stream.
	 *
	 * @param string $glue
	 * @return string
	 */
	public function join(string $glue): string {
		$inner_glue = '';
		$glued_values = '';
		foreach ($this as $value) {
			$glued_values .= $inner_glue . $value;
			$inner_glue = $glue;
		}
		return $glued_values;
	}

	/**
	 * Writes all elements into the given resource.
	 * This method consumes the stream.
	 *
	 * @param $resource
	 */
	public function intoResource($resource): void {
		foreach ($this as $value) {
			fwrite($resource, $value);
		}
	}

	/**
	 * Sum all elements of the stream.
	 * This method consumes the stream.
	 *
	 * @return int|float
	 */
	public function sum() {
		$sum = 0;
		foreach ($this as $value) {
			$sum += $value;
		}
		return $sum;
	}

	/**
	 * Get the min value from the stream. If two elements have the same value, the first one found is returned.
	 * This method consumes the stream.
	 *
	 * @param callable|null $compare_function By default, a comparison using PHP's <=> (spaceship) operator is performed.
	 *  You may provide a $compare_function returning -1, 0, or 1 for two given elements to change this behaviour.
	 * @return mixed
	 */
	public function min(callable $compare_function = null) {
		return $this->reduce(null, function ($acc, $value) use ($compare_function) {
			if (is_null($acc)) {
				return $value;
			}
			$to_apply_function = $compare_function ?? function ($a, $b) {
				return $b <=> $a;
			};

			$result = $to_apply_function($acc, $value);
			if ($result < 0) {
				return $value;
			}

			return $acc;
		});
	}

	/**
	 * Get the max value from the stream. If two elements have the same value, the first one found is returned.
	 * This method consumes the stream.
	 *
	 * @param callable|null $compare_function By default, a comparison using PHP's <=> (spaceship) operator is performed.
	 *  You may provide a $compare_function returning -1, 0, or 1 for two given elements to change this behaviour.
	 * @return mixed
	 */
	public function max(callable $compare_function = null) {
		return $this->min($compare_function ?? static function ($a, $b) {
			return $a <=> $b;
		});
	}

	/**
	 * Using the given accumulator, it calls the callback passing the accumulator and the value of the
	 * current element of the stream. The return of the callback is then saved into the accumulator and
	 * passed to the next element. Returns the accumulator when all elements have been consumed.
	 * This method consumes the stream.
	 *
	 * @param mixed $accumulator The initial value for the accumulator.
	 * @param callable $callback A callable in the form of (mixed $accumulator, mixed $item): mixed.
	 * @return mixed The final value of accumulator.
	 */
	public function reduce($accumulator, callable $callback) {
		foreach ($this as $value) {
			$accumulator = $callback($accumulator, $value);
		}
		return $accumulator;
	}

	/**
	 * Count the remaining items on the stream.
	 * This method consumes the stream.
	 *
	 * @return int The count of remaining items.
	 */
	public function count(): int {
		return $this->reduce(0, function ($count, $_) {
			return $count + 1;
		});
	}

	/**
	 * Concats the current stream with the given iterable.
	 *
	 * @param iterable $stream
	 * @return Stream
	 */
	public function concat(iterable $stream): Stream {
		$generator = function (iterable $stream) {
			foreach ($this as $element) {
				yield $element;
			}
			foreach ($stream as $element) {
				yield $element;
			}
		};
		return new self($generator($stream));
	}

	/**
	 * Calculates the transpose matrix for the current stream. All elements of the stream must be an iterable.
	 * If the elements don't have the same size, the size of the smaller element will be used.
	 * It does consumes the stream, but not the elements. So, if used in a stream of streams, only the main
	 * stream will be consumed.
	 *
	 * @return Stream
	 */
	public function transpose(): Stream {
		$streams = $this->map(function (iterable $iterable) {
			return Stream::of($iterable);
		})
			->collect();
		if (count($streams) == 0) {
			return new self(new \ArrayIterator([]));
		}

		$generator = function (\Iterator ...$streams): \Generator {
			foreach ($streams as $stream) {
				$stream->rewind();
			}
			$all_are_valid = function (\Iterator ...$streams) {
				foreach ($streams as $stream) {
					if (!$stream->valid()) {
						return false;
					}
				}
				return true;
			};

			while ($all_are_valid(...$streams)) {
				$ziped_element = [];
				foreach ($streams as $stream) {
					$ziped_element[] = $stream->current();
					$stream->next();
				}
				yield $ziped_element;
			}
		};
		return new self($generator(...$streams));
	}

	/**
	 * Executes a Cartesian product of the current stream (set A) with the given iterable (set B).
	 * The Cartesian product (AxB) returns subsets combining all elements of A with all elements of B.
	 * Each combination is contained in a array.
	 * It consumes the given stream.
	 *
	 * @param iterable $stream
	 * @return Stream
	 */
	public function cartesianProduct(iterable $stream): Stream {
		$generator = function ($set_b) {
			foreach ($this as $element_a) {
				foreach ($set_b as $element_b) {
					yield [$element_a, $element_b];
				}
			}
		};
		return new self($generator(iterator_to_array($stream)));
	}

	/**
	 * Execute the given callable against every stream element without changing each element.
	 * CAUTION: Since PHP likes to pass objects around as reference, this can result in the element
	 * being modified.
	 *
	 * Does not consumes the stream.
	 *
	 * @param callable $callback
	 * @return Stream
	 */
	public function inspect(callable $callback): Stream {
		return $this->map(function ($data) use ($callback) {
			$callback($data);
			return $data;
		});
	}

	/**
	 * Applies the callback to each element of the stream and return a stream with
	 * array pairs where the first element is the return of the callback and the second one
	 * is a Stream with all values for which the callback returned that value.
	 * The return of the callback can be a scalar or an object. If an object is returned
	 * we use its identity to group.
	 *
	 * Caution, even though this method returns a Stream, it does consumes the original
	 * Stream.
	 *
	 * @param callable $callback
	 * @return Stream
	 */
	public function groupBy(callable $callback): Stream {
		$values = [];
		$keys = [];
		foreach ($this as $value) {
			$key = $callback($value);
			$hashed_keys = is_object($key) ? spl_object_hash($key) : $key;
			$values[$hashed_keys][] = $value;
			$keys[$hashed_keys] = $key;
		}

		$generator = function (array $values, array $keys) {
			foreach ($keys as $hashed_keys => $key) {
				yield [$key, Stream::of($values[$hashed_keys])];
			}
		};

		return new Stream($generator($values, $keys));
	}

	/**
	 * Transform each stream element int a tuple (array of 2 elements) where
	 * the first element is the result of the callback applied to the element value
	 * and the second is the element value.
	 *
	 * @param callable $callback
	 * @return Stream
	 */
	public function keyBy(callable $callback): Stream {
		$generator = function (Stream $stream) use ($callback) {
			foreach ($stream as $value) {
				yield [$callback($value), $value];
			}
		};
		return new Stream($generator($this));
	}

	private static function range($start, $end, $step): self {
		$generatorAscending = function () use ($start, $end, $step) {
			for ($i = $start; $i <= $end; $i += $step) {
				yield $i;
			}
		};
		$generatorDescending = function () use ($start, $end, $step) {
			for ($i = $start; $i >= $end; $i -= $step) {
				yield $i;
			}
		};
		return new self($start < $end ? $generatorAscending() : $generatorDescending());
	}

}
