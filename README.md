### Why Stream?

Stream is a neat little library which should help you working with stream of data in PHP. It add methods to help you transform, reduce, and collect data.

### Is it stable?

Well, Stream is used by EBANX to process over 17 millions requests per day. So it should be stable enough. But, any problems with it please open an issue or better yet, create a PR to fix it.

### Example

Here is an example of how to use it. More examples can be found in the library test.

```PHP
use EBANX\Stream\Stream;

$result = Stream::rangeInt(0, 10)
    ->map(function (int $value): int {
        return $value ** 2;
    })
    ->filter(function (int $value): bool {
        return $value % 2 === 0;
    })
    ->collect();
```

### Licensing

We are distributing it using the permissive MIT license. Feel free to do whatever you want with it.
