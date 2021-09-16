<div align="center">
<img alt="Stellar" src="https://github.com/stellar/.github/raw/master/stellar-logo.png" width="558" />
<br/>
<strong>Creating equitable access to the global financial system</strong>
<h1>php-stellar-sdk</h1>
</div>

[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

PHP Stellar SDK provides APIs to build and sign transactions, connect and query [Stellar Horizon server](https://github.com/stellar/go/tree/master/services/horizon).

This library is under active development and should be considered beta quality. Please ensure that you've tested extensively on a test network and have added sanity checks in other places in your code.

The repository is a part of the [OpenSourceWebsite Organization](https://github.com/opensourcewebsite-org). This project and everyone participating in it is governed by the [Code of Conduct](CODE_OF_CONDUCT.md).

## Getting Started

[See the release notes for breaking changes](CHANGELOG.md).

See the [getting-started](getting-started/) directory for examples of how to use this library. Additional examples are available in the [examples](examples/) directory.

Please read through [Stellar API Documentation](https://developers.stellar.org/api) and [Stellar Testnet Documentation](https://developers.stellar.org/docs/glossary/testnet/).

## Contributing

Please read through our [Contribution Guidelines](CONTRIBUTING.md).

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require opensourcewebsite-org/php-stellar-sdk
```

or add

```
"opensourcewebsite-org/php-stellar-sdk": "*"
```

to the require section of your `composer.json` file.

## Usage

### Large Integer Support

The largest PHP integer is 64-bits when on a 64-bit platform. This is especially
important to pay attention to when working with large balance transfers. The native
representation of a single XLM (1 XLM) is 10000000 stroops.

Therefore, if you try to use a `MAX_INT` number of XLM (or a custom asset) it is
possible to overflow PHP's integer type when the value is converted to stroops and
sent to the network.

This library attempts to add checks for this scenario and also uses a `BigInteger`
class to work around this problem.

If your application uses large amounts of XLM or a custom asset please do extensive
testing with large values and use the `StellarAmount` helper class or the `BigInteger`
class if possible.

### Floating point issues

Although not specific to Stellar or PHP, it's important to be aware of problems
when doing comparisons between floating point numbers.

For example:

```php
$oldBalance = 1.605;
$newBalance = 1.61;

var_dump($oldBalance + 0.005);
var_dump($newBalance);
if ($oldBalance + 0.005 === $newBalance) {
    print "Equal\n";
}
else {
    print "Not Equal\n";
}
```

The above code considers the two values not to be equal even though the same value
is printed out:

Output:
```
float(1.61)
float(1.61)
Not Equal
```

To work around this issue, always work with and store amounts as an integer representing stroops. Only convert
back to a decimal number when you need to display a balance to the user.

The static `StellarAmount::STROOP_SCALE` property can be used to help with this conversion.

## Tests

``` bash
$ composer test
```

## License

This project is open source and available freely under the [MIT license](LICENSE.md).
