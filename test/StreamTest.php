<?php

namespace Test\EBANX\Stream;

use EBANX\Stream\Stream;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase {

	public function testStreamIsCountable(): void {
		$this->assertCount(0, Stream::of([]));
		$this->assertCount(1, Stream::of(['a']));
		$this->assertCount(3, Stream::of(['a', 'b', 'c']));
		$this->assertCount(2, Stream::of(['a', 'b', 'c'])->skip(1));
		$this->assertCount(2, Stream::of(['a', 'b', 'c'])->take(2));
	}

	public function testMin(): void {
		$this->assertEquals(0, Stream::of([0])->min());
		$this->assertEquals(2, Stream::of([3, 2, 2])->min());
		$this->assertEquals(3, Stream::of([3, 2, 2])->min(function ($a, $b) { return $a <=> $b;})); // min acting like max 🤷
		$this->assertEquals(['a' => 1], Stream::of([['a' => 2], ['a' => 1]])->min(function ($a, $b) { return $b['a'] <=> $a['a'];}));
	}

	public static function minByTestCases(): \Generator {
		$key_function = function ($data) {
			return $data['key'];
		};

		yield 'Not itens, returns null' =>
			[null, [], $key_function];
		yield 'Single item' =>
			[['key' => 0, 'val' => 999], [['key' => 0, 'val' => 999]], $key_function];
		yield 'Multiple items returns first' =>
			[['key' => 2, 'val' => 2], [['key'=> 3, 'val' => 0], ['key' => 2, 'val' => 2], ['key' => 2, 'val' => 1]], $key_function];
	}

	/** @dataProvider minByTestCases */
	public function testMinBy(?array $expected_min, array $data, callable $key_function): void {
		$this->assertEquals($expected_min, Stream::of($data)->minBy($key_function));
	}

	public static function maxByTestCases(): \Generator {
		$key_function = function ($data) {
			return $data['key'];
		};

		yield 'Not itens, returns null' =>
			[null, [], $key_function];
		yield 'Single item' =>
			[['key' => 0, 'val' => 999], [['key' => 0, 'val' => 999]], $key_function];
		yield 'Multiple items returns first' =>
			[['key' => 3, 'val' => 4], [['key'=> 3, 'val' => 4], ['key' => 3, 'val' => 2], ['key' => 2, 'val' => 1]], $key_function];
	}

	/** @dataProvider maxByTestCases */
	public function testMaxBy(?array $expected_max, array $data, callable $key_function): void {
		$this->assertEquals($expected_max, Stream::of($data)->maxBy($key_function));
	}

	public function testMax(): void {
		$this->assertEquals(0, Stream::of([0])->max());
		$this->assertEquals(3, Stream::of([3, 2, 2])->max());
		$this->assertEquals(2, Stream::of([3, 2, 2])->max(function ($a, $b) { return $b <=> $a;})); // max acting like min 🤷
		$this->assertEquals(['a' => 2], Stream::of([['a' => 2], ['a' => 1]])->max(function ($a, $b) { return $a['a'] <=> $b['a'];}));
	}

	public function testChainMethods(): void {
		$result = Stream::rangeInt(1, 100)
			->take(20)
			->filter(function (int $value) {
				return $value % 2 !== 0;
			})
			->map(function (int $value) {
				return [ 'result' => $value + 2];
			})
			->pluck('result')
			->chunkEvery(5)
			->collect();
		$this->assertEquals([
			[3, 5, 7, 9, 11],
			[13, 15, 17, 19, 21],
		], $result);
	}

	public function testCollectFirst(): void {
		$this->assertEquals(1, Stream::of([1, 2, 3])->collectFirst());
		$this->assertEquals(2, Stream::of([1, 2, 3])->skip(1)->collectFirst());
	}

	public function testCollectFirstWithoutElements_ShouldRaiseException(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('No elements available in this stream.');
		Stream::of([1, 2, 3])->skip(3)->collectFirst();
	}

	public function testCollectFirst_ShouldReturnDefault_WithoutCallbackWithDefault(): void {
		$result = Stream::of([1, 2, 3])->skip(3)->collectFirst(null, $default = 0);
		$this->assertEquals($default, $result);
	}

	public function testTakeShouldConsumeOnlyDesiredValues(): void {
		$source = Stream::rangeInt(1, 4);

		$first_2_stream = $source->take(2);

		$this->assertEquals([1, 2], $first_2_stream->collect());
		$this->assertSourceIteratorWasNotFullyConsumed($source);

		$remaining_stream = $source->take(2);
		$this->assertStreamIsNotConsumableAnymore($remaining_stream);
	}

	public function testOf(): void {
		$result = Stream::of(new \ArrayIterator([1, 2, 3, 4]))
			->collect();
		$this->assertEquals([1, 2, 3, 4], $result);
	}

	public function testOf_ShouldAcceptPrimitiveArray(): void {
		$result = Stream::of([1, 2, 3, 4])
			->collect();
		$this->assertEquals([1, 2, 3, 4], $result);
	}

	public function testOf_ShouldReturnAnIteratorWithoutKeys(): void {
		$result_array = iterator_to_array(Stream::of(['a' => 1, 'b' => 2, 'c' => 3]));
		$this->assertEquals([1, 2, 3], $result_array);
	}

	public function testCollect(): void {
		$result = Stream::rangeInt(1, 4)
			->collect();
		$this->assertEquals([1, 2, 3, 4], $result);
	}

	public function testRange(): void {
		$range_1 = Stream::rangeInt(0, 4)
			->collect();
		$range_2 = Stream::rangeFloat(1, 2, 0.25)
			->collect();
		$range_3 = Stream::rangeInt(5, 0)
			->collect();
		$this->assertEquals([0, 1, 2, 3, 4], $range_1);
		$this->assertEquals([1, 1.25, 1.5, 1.75, 2], $range_2);
		$this->assertEquals([5, 4, 3, 2, 1, 0], $range_3);
	}

	public function testMap(): void {
		$result = Stream::rangeInt(1, 4)
			->map(function (int $value) {
				return $value * 2;
			})
			->collect();
		$this->assertEquals([2, 4, 6, 8], $result);
	}

	public function testFlatMap(): void {
		$result = Stream::rangeInt(1, 4)
			->flatMap(function (int $value) {
				return Stream::rangeInt(0, $value);
			})
			->collect();
		$this->assertEquals([0, 1, 0, 1, 2, 0, 1, 2, 3, 0, 1, 2, 3, 4], $result);
	}

	public function testFilter(): void {
		$result = Stream::rangeInt(1, 4)
			->filter(function (int $value) {
				return $value % 2 === 0;
			})
			->collect();
		$this->assertEquals([2, 4], $result);
	}

	public function testTake(): void {
		$result = Stream::rangeInt(1, 15)
			->take(5)
			->collect();
		$this->assertEquals([1, 2, 3, 4, 5], $result);
	}

	public function testTakeZeroElement(): void {
		$iterator_that_counts_calls = $this->createIteratorThatCountCalls(Stream::rangeInt(1, 15));
		$this->assertEquals([], Stream::of($iterator_that_counts_calls)->take(0)->collect());
		self::assertEquals(0, $iterator_that_counts_calls->rewind_calls);
		self::assertEquals(0, $iterator_that_counts_calls->next_calls);
		self::assertEquals(0, $iterator_that_counts_calls->current_calls);
	}

	public function testTake_ShouldOnlyConsumeTheExpectedAmountOfElements(): void {
		$range = Stream::rangeInt(1, 10);
		$iterator_that_counts_calls = $this->createIteratorThatCountCalls($range);
		self::assertEquals([1, 2, 3, 4, 5], Stream::of($iterator_that_counts_calls)->take(5)->collect());
		self::assertEquals(1, $iterator_that_counts_calls->rewind_calls);
		self::assertEquals(4, $iterator_that_counts_calls->next_calls);
		self::assertEquals(5, $iterator_that_counts_calls->current_calls);
	}

	public function testSkip(): void {
		$result = Stream::rangeInt(1, 15)
			->skip(10)
			->collect();
		$this->assertEquals([11, 12, 13, 14, 15], $result);
	}

	public function testChunkEvery(): void {
		$result = Stream::rangeInt(1, 9)
			->chunkEvery(4)
			->collect();
		$this->assertEquals([
			[1, 2, 3, 4],
			[5, 6, 7, 8],
			[9],
		], $result);
	}

	public function testForEach(): void {
		$result = '';
		Stream::rangeInt(1, 5)
			->forEach(function (int $value) use (&$result){
				$result .= 'Value: ' . $value . PHP_EOL;
			});
		$expected_result = <<<OUTPUT
Value: 1
Value: 2
Value: 3
Value: 4
Value: 5

OUTPUT;
		self::assertEquals($expected_result, $result);
	}

	public function testJoin(): void {
		$result_1 = Stream::rangeInt(1, 5)
			->join(', ');
		$result_2 = Stream::of(new \ArrayIterator(['', '2', '3']))
			->join(' - ');
		$this->assertEquals('1, 2, 3, 4, 5', $result_1);
		$this->assertEquals(' - 2 - 3', $result_2);
	}

	public function testIntoResource(): void {
		$resource = fopen('php://temp', 'r+');
		Stream::rangeInt(1, 5)
			->intoResource($resource);
		rewind($resource);
		$this->assertEquals('12345', stream_get_contents($resource));
	}

	public function testSum(): void {
		$sum_1 = Stream::rangeInt(1, 5)
			->sum();
		$sum_2 = Stream::rangeFloat(1, 2, 0.5)
			->sum();
		$this->assertEquals(15, $sum_1);
		$this->assertEquals(4.5, $sum_2);
	}

	public function testReduce(): void {
		$result = Stream::rangeInt(1, 5)
			->reduce(0, function (int $accumulator, int $value) {
				return $accumulator += $value;
			});
		$this->assertEquals(15, $result);
	}

	public function testTranspose(): void {
		$original_data = [[0, 1, 2], [2, 1, 0], ['a', 'b', 'c']];
		$result = Stream::of(new \ArrayIterator($original_data))
			->transpose()
			->collect();
		$this->assertEquals([[0, 2, 'a'], [1, 1, 'b'], [2, 0, 'c']], $result);
	}

	public function testTransposeTranspose_ShouldGetOriginalArray(): void {
		$original_data = [[0, 1, 2], [2, 1, 0], ['a', 'b', 'c']];
		$result = Stream::of(new \ArrayIterator($original_data))
			->transpose()
			->transpose()
			->collect();
		$this->assertEquals($original_data, $result);
	}

	public function testTransposeWithElementsOfDifferentLength_ShouldUseLengthOfTheSmallerElement(): void {
		$original_data = [[0, 1, 2], [2, 1, 0], ['a', 'b']];
		$result = Stream::of(new \ArrayIterator($original_data))
			->transpose()
			->collect();
		$this->assertEquals([[0, 2, 'a'], [1, 1, 'b']], $result);
	}

	public function testTransposeOnEmptyStream_ShouldReturnEmptyStream(): void {
		$result = Stream::of([])
			->transpose()
			->collect();
		self::assertEquals([], $result);
	}

	public function testConcat(): void {
		$stream_1 = Stream::rangeInt(0, 3);
		$stream_2 = Stream::rangeInt(4, 6);
		$stream_3 = Stream::of(new \ArrayIterator(['a', 'b']));
		$result = $stream_1->concat($stream_2)
			->concat($stream_3)
			->collect();
		$this->assertEquals([0, 1, 2, 3, 4, 5, 6, 'a', 'b'], $result);
	}

	public function testSkipWhile(): void {
		$iterable = new \ArrayIterator([0, 1, 2, 'a', 'b', 'c', 4, 5, 6]);
		$result = Stream::of($iterable)
			->skipWhile(static function($item) {
				return is_numeric($item);
			})->collect();

		self::assertEquals(['a', 'b', 'c', 4, 5, 6], $result);
	}

	public function testSkipWhileWithNoMatching(): void {
		$iterable = new \ArrayIterator([0, 1, 2, 3, 4, 5, 6]);
		$result = Stream::of($iterable)
			->skipWhile(static function($item) {
				return is_string($item);
			})->collect();

		self::assertEquals([0, 1, 2, 3, 4, 5, 6], $result);
	}

	public function testSkipWhileWithAllMatching(): void {
		$iterable = new \ArrayIterator([0, 1, 2, 3, 4, 5, 6]);
		$result = Stream::of($iterable)
			->skipWhile(static function($item) {
				return is_numeric($item);
			})->collect();

		self::assertEmpty( $result);
	}

	public function testChunkBy(): void {
		$result = Stream::of([1, 2, 3, 4, 5, 6, 7, 8])
			->chunkBy(function (int $element) {
				return $element <= 2 || $element >= 6;
			})
			->collect();
		self::assertEquals([[1, 2], [3, 4, 5], [6, 7, 8]], $result);
	}

	public function testChunkByInAnEmptyStream_ShouldReturnAndEmptyStreamAndDontCallTheTestFunction(): void {
		$was_test_function_called = false;
		$result = Stream::of([])
			->chunkBy(function ($element) use (&$was_test_function_called) {
				$was_test_function_called = true;
				return $element;
			})
			->collect();
		self::assertEquals([], $result);
		self::assertFalse($was_test_function_called);
	}

	public function testCartesianProduct_SameSizes(): void {
		$stream_1 = Stream::rangeInt(1, 3);
		$stream_2 = Stream::of(new \ArrayIterator(['a', 'b', 'c']));

		$result = $stream_1->cartesianProduct($stream_2)
			->collect();
		self::assertEquals(
			[
				[1, 'a'], [1, 'b'], [1, 'c'],
				[2, 'a'], [2, 'b'], [2, 'c'],
				[3, 'a'], [3, 'b'], [3, 'c'],
			],
			$result
		);
	}

	public function testCartesianProduct_DifferentSizes(): void {
		$stream_1 = Stream::rangeInt(1, 4);
		$stream_2 = Stream::of(new \ArrayIterator(['a', 'b']));

		$result = $stream_1->cartesianProduct($stream_2)
			->collect();
		self::assertEquals(
			[
				[1, 'a'], [1, 'b'],
				[2, 'a'], [2, 'b'],
				[3, 'a'], [3, 'b'],
				[4, 'a'], [4, 'b'],
			],
			$result
		);
	}

	public function testInspect(): void {
		$stream = Stream::rangeInt(1, 5);
		$output = '';
		$result = $stream->inspect(function (int $number) use (&$output) {
			$output .= $number . ', ';
		})
			->collect();
		self::assertEquals([1, 2, 3, 4, 5], $result);
		self::assertEquals('1, 2, 3, 4, 5, ', $output);
	}

	public function testGroupBy(): void {
		$stream = [
			['group' => 'payment', 'value' => 1],
			['group' => 'payment', 'value' => 2],
			['group' => 'remittance', 'value' => 1]
		];

		$result = Stream::of($stream)
			->groupBy(function (array $list) {
				return $list['group'];
			})
			->map(function (array $grouped) {
				[$key, $group] = $grouped;
				return [$key, $group->collect()];
			})
			->collectAsKeyValue();

		self::assertEquals([
			'payment' => [
				['group' => 'payment', 'value' => 1],
				['group' => 'payment', 'value' => 2]
			],
			'remittance' => [
				['group' => 'remittance', 'value' => 1]
			]
		], $result);
	}

	public function testGroupByWithObjects_ShouldWork(): void {
		$object = (object)['value' => 2];
		$values = [
			['key' => 'first'],
			['key' => (object)['value' => 1]],
			['key' => (object)['value' => 1]],
			['key' => $object],
			['key' => $object],
		];

		$result = Stream::of($values)
			->groupBy(function (array $list) {
				return $list['key'];
			})
			->map(function (array $grouped) {
				[$key, $group] = $grouped;
				return [$key, $group->collect()];
			})
			->collect();
		self::assertEquals(
			[
				['first', [['key' => 'first']]],
				[(object)['value' => 1], [['key' => (object)['value' => 1]]]],
				[(object)['value' => 1], [['key' => (object)['value' => 1]]]],
				[$object, [['key' => $object], ['key' => $object]]]
			], $result
		);
	}

	public function testCollectAsKeyValue(): void {
		$result = Stream::rangeInt(1, 5)
			->map(function (int $n) {
				return [$n, $n ** 2];
			})
			->collectAsKeyValue();

		self::assertEquals([
			1 => 1,
			2 => 4,
			3 => 9,
			4 => 16,
			5 => 25
		], $result);
	}

	public function testKeyBy(): void {
		$result = Stream::rangeInt(1, 5)
			->keyBy(function (int $i) {
				return $i + 10;
			})
			->collectAsKeyValue();
		self::assertEquals([
			11 => 1,
			12 => 2,
			13 => 3,
			14 => 4,
			15 => 5,
		], $result);
	}

	public function testCollectFirst_WithMatchingCallback(): void {
		$elements = [
			['description' => 'False, should not return 1', 'condition' => false],
			['description' => 'False, should not return 2', 'condition' => false],
			$expected_element = ['description' => 'True, should return', 'condition' => true],
			['description' => 'True, after first is met, should not return', 'condition' => true],
		];

		$collected_first = Stream::of($elements)
			->collectFirst(function ($element) {
				return $element['condition'];
			});

		self::assertEquals($expected_element, $collected_first);
	}

	public function testCollectFirst_WithNoMatchingCallback_WithDefaultReturn(): void {
		$elements = [
			['description' => 'A', 'condition' => false],
			['description' => 'B', 'condition' => false],
			['description' => 'C', 'condition' => false],
			['description' => 'D', 'condition' => false],
		];

		$collected_first = Stream::of($elements)
			->collectFirst(function ($element) {
				return $element['condition'];
			}, $default = 'Nothing found.');

		self::assertEquals('Nothing found.', $collected_first);
	}

	public function testCollectFirst_WithNoMatchingCallback_WithoutDefaultReturn(): void {
		$elements = [
			['description' => 'A', 'condition' => false],
			['description' => 'B', 'condition' => false],
		];

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('No element matching the criteria was found.');
		Stream::of($elements)
			->collectFirst(function ($element) {
				return $element['condition'];
			});
	}

	public function testCollectFirst_WithNoMatchingCallback_WithEmptyStringReturn(): void {
		$elements = [
			['description' => 'A', 'condition' => false],
			['description' => 'B', 'condition' => false],
		];

		$collected_first = Stream::of($elements)
			->collectFirst(function ($element) {
				return $element['condition'];
			}, $default = '');

		self::assertEquals($default, $collected_first);
	}

	public function testCollectLast_WithoutCallback_ShouldReturnLastElement(): void {
		$elements = [
			['description' => 'A'],
			['description' => 'B'],
			['description' => 'C'],
			$expected_element = ['description' => 'D'],
		];

		$collected_last = Stream::of($elements)
			->collectLast();

		self::assertEquals($expected_element, $collected_last);
	}

	public function testCollectLast_WithMatchingCallback(): void {
		$elements = [
			['description' => 'False, should not return 1', 'condition' => false],
			['description' => 'False, should not return 2', 'condition' => false],
			['description' => 'True, should not return, not last', 'condition' => true],
			$expected_element = ['description' => 'True, should return', 'condition' => true],
			['description' => 'False, should not return 3', 'condition' => false],
		];

		$collected_last = Stream::of($elements)
			->collectLast(function ($element) {
				return $element['condition'];
			});

		self::assertEquals($expected_element, $collected_last);
	}

	public function testCollectLast_WithNoMatchingCallback_WithDefaultReturn(): void {
		$elements = [
			['description' => 'A', 'condition' => false],
			['description' => 'B', 'condition' => false],
			['description' => 'C', 'condition' => false],
			['description' => 'D', 'condition' => false],
		];

		$collected_last = Stream::of($elements)
			->collectLast(function ($element) {
				return $element['condition'];
			}, $default = 'Nothing found.');

		self::assertEquals('Nothing found.', $collected_last);
	}

	public function testCollectLast_WithNoMatchingCallback_WithoutDefaultReturn(): void {
		$elements = [
			['description' => 'A', 'condition' => false],
			['description' => 'B', 'condition' => false],
		];

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('No element was found.');
		Stream::of($elements)
			->collectLast(function ($element) {
				return $element['condition'];
			});
	}

	public function testPluck_WithAssociativeArray(): void {
		$elements = [
			['description' => 'A'],
			['description' => 'B'],
		];

		$result = Stream::of($elements)
			->pluck('description')
			->collect();
		self::assertEquals(['A','B'], $result);
	}

	public function testPluck_WithObject(): void {
		$elements = [
			(object)['description' => 'A'],
			(object)['description' => 'B'],
		];

		$result = Stream::of($elements)
			->pluck('description')
			->collect();
		self::assertEquals(['A','B'], $result);
	}

	public function testPluck_WithIntKey(): void {
		$elements = [
			['A'],
			['B'],
		];
		$result = Stream::of($elements)
			->pluck(0)
			->collect();
		self::assertEquals(['A','B'], $result);
	}

	public function testPluck_WithInvalidKey(): void {
		$elements = [
			(object)['description' => 'A'],
			(object)['description' => 'B'],
		];
		$result = Stream::of($elements)
			->pluck(0)
			->collect();
		self::assertEquals([], $result);
	}

	public function testFlatten(): void {
		$elements = [
			[1, 2],
			Stream::of([3, 4, 5]),
			(object) ['a' => 6, 'b' => 7],
			[[8, 9]]
		];
		$result = Stream::of($elements)
			->flatten()
			->collect();
		self::assertEquals(
			[1, 2, 3, 4, 5, 6, 7, [8, 9]],
			$result
		);
	}

	public function testTakeWhile_ShouldReturnFirstMatchingElements_ShouldNotIterateOverWholeStream(): void {
		$iterable = new \ArrayIterator([1.25, 2.75, 3, 1, 1, 5, 8]);
		$total = 0;
		$elements_checked = [];
		$result = Stream::of($iterable)
			->inspect(function($value) use (&$elements_checked) {
				$elements_checked[] = $value;
			})
			->takeWhile(function($value) use (&$total) {
				$total = $total + $value;
				return $total <= 8;
			})->collect();

		self::assertEquals([1.25, 2.75, 3, 1], $result);
		self::assertEquals([1.25, 2.75, 3, 1, 1], $elements_checked);
	}

	public function testIntoGenerator(): void {
		$stream = Stream::of([1, 2, 3, 4, 5]);
		$generator = $stream->intoGenerator();
		self::assertInstanceOf(\Generator::class, $generator);
		self::assertEquals([1, 2, 3, 4, 5], iterator_to_array($generator));
	}

	public function testReject(): void {
		$result = Stream::rangeInt(1, 10)
			->reject(function (int $value): bool {
				return $value % 2 === 0;
			})
			->collect();

		$this->assertEquals([1, 3, 5, 7, 9], $result);
	}

	public function testAll_GivenAllMatchedElements_ShouldReturnTrue(): void {
		$stream = Stream::of([0, 2, 4, 6, 8]);
		$result = $stream
			->all(function (int $value): bool {
				return $value % 2 === 0;
			});

		$this->assertTrue($result);
	}

	public function testAll_GivenOneMismatchedElement_ShouldReturnFalse(): void {
		$result = Stream::rangeInt(1, 10)
			->all(function (int $value): bool {
				return $value < 10;
			});

		$this->assertFalse($result);
	}

	public function testNone_GivenAllMismatchedElements_ShouldReturnTrue(): void {
		$stream = Stream::of([0, 2, 4, 6, 8]);
		$result = $stream
			->none(function (int $value): bool {
				return $value % 2 === 1;
			});

		$this->assertTrue($result);
	}

	public function testNone_GivenOneMismatchedElement_ShouldReturnFalse(): void {
		$stream = Stream::of([0, 2, 4, 6, 8, 9]);
		$result = $stream
			->none(function (int $value): bool {
				return $value % 2 === 0;
			});

		$this->assertFalse($result);
	}

	public function testAny_GiveAtLeastOneElementMatching_ShouldReturnTrue(): void {
		$stream = Stream::of([0, 2, 4, 6, 8, 9]);
		$result = $stream
			->any(function (int $value): bool {
				return $value % 2 === 1;
			});

		$this->assertTrue($result);
	}

	public function testAny_GiveThatNoElementMatches_ShouldReturnFalse(): void {
		$stream = Stream::of([0, 2, 4, 6, 8, 9]);
		$result = $stream
			->any(function (int $value): bool {
				return $value > 9;
			});

		$this->assertFalse($result);
	}

	private function createIteratorThatCountCalls(\Iterator $range) {
		return new class($range) implements \Iterator {
			public $rewind_calls = 0;
			public $next_calls = 0;
			public $current_calls = 0;
			private $inner_iterator;

			public function __construct(\Iterator $inner_iterator) {
				$this->inner_iterator = $inner_iterator;
			}

			public function current() {
				$this->current_calls++;
				return $this->inner_iterator->current();
			}

			public function next() {
				$this->next_calls++;
				$this->inner_iterator->next();
			}

			public function key() {
				return $this->inner_iterator->key();
			}

			public function valid() {
				return $this->inner_iterator->valid();
			}

			public function rewind() {
				$this->rewind_calls++;
				$this->inner_iterator->rewind();
			}
		};
	}

	private function assertStreamIsNotConsumableAnymore(Stream $remaining_stream): void {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Cannot rewind a generator that was already run');
		$remaining_stream->collect();
	}

	private function assertSourceIteratorWasNotFullyConsumed(Stream $source): void {
		$this->assertTrue($source->valid(), 'Source iterator was fully consumed, although there is no need for it');
	}
}
