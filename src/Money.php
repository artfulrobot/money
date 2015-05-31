<?php

namespace Brick\Money;

use Brick\Money\Currency;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Brick\Math\ArithmeticException;

/**
 * Represents a monetary value in a given currency. This class is immutable.
 */
class Money
{
    /**
     * The amount, with a scale matching the currency's fraction digits.
     *
     * @var \Brick\Math\BigDecimal
     */
    private $amount;

    /**
     * The currency.
     *
     * @var \Brick\Money\Currency
     */
    private $currency;

    /**
     * Class constructor.
     *
     * @param BigDecimal $amount   The amount, with scale matching the currency's fraction digits.
     * @param Currency   $currency The currency.
     */
    private function __construct(BigDecimal $amount, Currency $currency)
    {
        $this->amount   = $amount;
        $this->currency = $currency;
    }

    /**
     * Returns the minimum of the given values.
     *
     * @param Money ...$monies
     *
     * @return \Brick\Money\Money
     *
     * @throws CurrencyMismatchException If all the monies are not in the same currency.
     * @throws \InvalidArgumentException If the money list is empty.
     */
    public static function min(Money ...$monies)
    {
        $min = null;

        foreach ($monies as $money) {
            if ($min === null || $money->isLessThan($min)) {
                $min = $money;
            }
        }

        if ($min === null) {
            throw new \InvalidArgumentException('min() expects at least one Money.');
        }

        return $min;
    }

    /**
     * Returns the maximum of the given values.
     *
     * @param Money ...$monies
     *
     * @return \Brick\Money\Money
     *
     * @throws CurrencyMismatchException If all the monies are not in the same currency.
     * @throws \InvalidArgumentException If the money list is empty.
     */
    public static function max(Money ...$monies)
    {
        $max = null;

        foreach ($monies as $money) {
            if ($max === null || $money->isGreaterThan($max)) {
                $max = $money;
            }
        }

        if ($max === null) {
            throw new \InvalidArgumentException('max() expects at least one Money.');
        }

        return $max;
    }

    /**
     * @param Money|BigDecimal|number|string $amount       A Money instance or decimal amount.
     * @param Currency|string                $currency     A Currency instance or currency code.
     * @param int                            $roundingMode The rounding mode to use.
     *
     * @return Money
     *
     * @throws CurrencyMismatchException If a money used as amount does not match the given currency.
     * @throws ArithmeticException       If the scale exceeds the currency scale and no rounding is requested.
     * @throws \InvalidArgumentException If an invalid rounding mode is given.
     */
    public static function of($amount, $currency, $roundingMode = RoundingMode::UNNECESSARY)
    {
        $currency = Currency::of($currency);

        if ($amount instanceof Money) {
            $amount->checkCurrency($currency);

            return $amount;
        }

        $scale  = $currency->getDefaultFractionDigits();
        $amount = BigDecimal::of($amount)->withScale($scale, $roundingMode);

        return new Money($amount, $currency);
    }

    /**
     * @todo rename: cents is not appropriate
     *
     * @param BigInteger|int|string $cents    The integer amount in cents.
     * @param Currency|string       $currency The currency.
     *
     * @return Money
     */
    public static function ofCents($cents, $currency)
    {
        $currency = Currency::of($currency);
        $amount   = BigDecimal::ofUnscaledValue($cents, $currency->getDefaultFractionDigits());

        return new Money($amount, $currency);
    }

    /**
     * Parses a string representation of a money, such as USD 23.00.
     *
     * @param Money|string $string
     *
     * @return \Brick\Money\Money
     *
     * @throws \Brick\Money\MoneyParseException If the parsing fails.
     */
    public static function parse($string)
    {
        if ($string instanceof Money) {
            return $string;
        }

        $parts = explode(' ', $string);

        if (count($parts) != 2) {
            throw MoneyParseException::invalidFormat($string);
        }

        try {
            $currency = Currency::of($parts[0]);
            $amount   = BigDecimal::of($parts[1]);
        }
        catch (\InvalidArgumentException $e) {
            throw MoneyParseException::wrap($e);
        }

        try {
            return Money::of($amount, $currency);
        }
        catch (ArithmeticException $e) {
            throw MoneyParseException::wrap($e);
        }
    }

    /**
     * Returns a Money with zero value, in the given Currency.
     *
     * @param Currency|string $currency
     *
     * @return \Brick\Money\Money
     */
    public static function zero($currency)
    {
        return Money::of(0, $currency);
    }

    /**
     * @param Currency|string $currency
     *
     * @return void
     *
     * @throws CurrencyMismatchException
     */
    public function checkCurrency($currency)
    {
        $currency = Currency::of($currency);

        if (! $this->currency->isEqualTo($currency)) {
            throw CurrencyMismatchException::currencyMismatch($this->currency, $currency);
        }
    }

    /**
     * Returns the Currency of this Money.
     *
     * @return \Brick\Money\Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Returns the amount of this Money, as a Decimal.
     *
     * @return \Brick\Math\BigDecimal
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param Money|BigDecimal|number|string $that
     *
     * @return \Brick\Money\Money
     */
    public function plus($that)
    {
        $that = Money::of($that, $this->currency);

        return new Money($this->amount->plus($that->amount), $this->currency);
    }

    /**
     * @param Money|BigDecimal|number|string $that
     *
     * @return \Brick\Money\Money
     */
    public function minus($that)
    {
        $that = Money::of($that, $this->currency);

        return new Money($this->amount->minus($that->amount), $this->currency);
    }

    /**
     * @param BigDecimal|number|string $that
     * @param int                      $roundingMode
     *
     * @return \Brick\Money\Money
     */
    public function multipliedBy($that, $roundingMode = RoundingMode::UNNECESSARY)
    {
        $that = BigDecimal::of($that);

        $scale  = $this->currency->getDefaultFractionDigits();
        $amount = $this->amount->multipliedBy($that)->withScale($scale, $roundingMode);

        return new Money($amount, $this->currency);
    }

    /**
     * @param BigDecimal|number|string $that
     * @param int                      $roundingMode
     *
     * @return \Brick\Money\Money
     */
    public function dividedBy($that, $roundingMode = RoundingMode::UNNECESSARY)
    {
        $that = BigDecimal::of($that);

        $scale  = $this->currency->getDefaultFractionDigits();
        $amount = $this->amount->dividedBy($that, $scale, $roundingMode);

        return new Money($amount, $this->currency);
    }

    /**
     * Returns a copy of this Money with the value negated.
     *
     * @return \Brick\Money\Money
     */
    public function negated()
    {
        return new Money($this->amount->negated(), $this->currency);
    }

    /**
     * Returns whether this Money has zero value.
     *
     * @return bool
     */
    public function isZero()
    {
        return $this->amount->isZero();
    }

    /**
     * Returns whether this Money has a negative value.
     *
     * @return bool
     */
    public function isNegative()
    {
        return $this->amount->isNegative();
    }

    /**
     * Returns whether this Money has a negative or zero value.
     *
     * @return bool
     */
    public function isNegativeOrZero()
    {
        return $this->amount->isNegativeOrZero();
    }

    /**
     * Returns whether this Money has a positive value.
     *
     * @return bool
     */
    public function isPositive()
    {
        return $this->amount->isPositive();
    }

    /**
     * Returns whether this Money has a positive or zero value.
     *
     * @return bool
     */
    public function isPositiveOrZero()
    {
        return $this->amount->isPositiveOrZero();
    }

    /**
     * Compares this Money to the given Money.
     *
     * @param Money|BigDecimal|number|string $that
     *
     * @return int -1, 0 or 1.
     *
     * @throws CurrencyMismatchException
     */
    public function compareTo($that)
    {
        $that = Money::of($that, $this->currency);

        return $this->amount->compareTo($that->amount);
    }

    /**
     * Returns whether this Money is equal to the given Money.
     *
     * @param Money|BigDecimal|number|string $that
     *
     * @return bool
     *
     * @throws CurrencyMismatchException
     */
    public function isEqualTo($that)
    {
        $that = Money::of($that, $this->currency);

        return $this->amount->isEqualTo($that->amount);
    }

    /**
     * Returns whether this Money is less than the given amount.
     *
     * @param Money|BigDecimal|number|string $that
     *
     * @return bool
     *
     * @throws CurrencyMismatchException
     */
    public function isLessThan($that)
    {
        $that = Money::of($that, $this->currency);

        return $this->amount->isLessThan($that->amount);
    }

    /**
     * Returns whether this Money is less than or equal to the given amount.
     *
     * @param Money|BigDecimal|number|string $that
     *
     * @return bool
     *
     * @throws CurrencyMismatchException
     */
    public function isLessThanOrEqualTo($that)
    {
        $that = Money::of($that, $this->currency);

        return $this->amount->isLessThanOrEqualTo($that->amount);
    }

    /**
     * Returns whether this Money is greater than the given Money.
     *
     * @param Money|BigDecimal|number|string $that
     *
     * @return bool
     *
     * @throws CurrencyMismatchException
     */
    public function isGreaterThan($that)
    {
        $that = Money::of($that, $this->currency);

        return $this->amount->isGreaterThan($that->amount);
    }

    /**
     * Returns whether this Money is greater than or equal to the given Money.
     *
     * @param Money|BigDecimal|number|string $that
     *
     * @return bool
     *
     * @throws CurrencyMismatchException
     */
    public function isGreaterThanOrEqualTo($that)
    {
        $that = Money::of($that, $this->currency);

        return $this->amount->isGreaterThanOrEqualTo($that->amount);
    }

    /**
     * Returns a string containing the major value of the money.
     *
     * Example: 123.45 will return '123'.
     *
     * @return string
     */
    public function getAmountMajor()
    {
        return $this->amount->withScale(0, RoundingMode::DOWN)->getUnscaledValue();
    }

    /**
     * Returns a string containing the minor value of the money.
     *
     * Example: 123.45 will return '45'.
     *
     * @return string
     */
    public function getAmountMinor()
    {
        return substr($this->amount->getUnscaledValue(), - $this->currency->getDefaultFractionDigits());
    }

    /**
     * Returns a string containing the value of this money in cents.
     *
     * Example: 123.45 USD will return '12345'.
     *
     * @return string
     */
    public function getAmountCents()
    {
        return $this->amount->getUnscaledValue();
    }

    /**
     * Formats this Money with the given NumberFormatter.
     *
     * Note that NumberFormatter internally represents values using floating point arithmetic,
     * so discrepancies can appear when formatting very large monetary values.
     *
     * @param \NumberFormatter $formatter
     *
     * @return string
     */
    public function formatWith(\NumberFormatter $formatter)
    {
        return $formatter->formatCurrency(
            (string) $this->amount,
            (string) $this->currency
        );
    }

    /**
     * Formats this Money to the given locale.
     *
     * Note that this method uses NumberFormatter, which internally represents values using floating point arithmetic,
     * so discrepancies can appear when formatting very large monetary values.
     *
     * @param string $locale
     *
     * @return string
     */
    public function formatTo($locale)
    {
        return $this->formatWith(new \NumberFormatter($locale, \NumberFormatter::CURRENCY));
    }

    /**
     * Returns a non-localized string representation of this Money
     * e.g. "EUR 25.00"
     *
     * @return string
     */
    public function toString()
    {
        return $this->currency->getCode() . ' ' . $this->amount;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
