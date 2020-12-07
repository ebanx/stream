<?php

namespace Test\EBANX;

use EBANX\Stream\Stream;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase {

	public function testChainMethods(): void {
		$result = Stream::rangeInt(1, 100)
			->take(20)
			->filter(function (int $value) {
				return $value % 2 !== 0;
			})
			->map(function (int $value) {
				return $value + 2;
			})
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

	private function assertStreamIsNotConsumableAnymore(Stream $remaining_stream): void {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Cannot rewind a generator that was already run');
		$remaining_stream->collect();
	}

	private function assertSourceIteratorWasNotFullyConsumed(Stream $source): void {
		$this->assertTrue($source->valid(), 'Source iterator was fully consumed, although there is no need for it');
	}
}
