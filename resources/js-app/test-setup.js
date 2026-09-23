import '@testing-library/jest-dom';
import { configure } from '@testing-library/react';

/**
 * How long `waitFor` and `findBy*` keep trying.
 *
 * Testing Library's default is one second, which is generous on a developer's
 * machine and not always enough on a CI runner sharing itself between workers.
 * Three different tests failed there on assertions that pass locally every
 * time — a save that had not been posted yet, a select whose options had not
 * arrived, an alert not yet rendered — and each was green on a re-run with no
 * change. That is a clock, not a bug, and a suite that fails at random costs
 * more than a slow one.
 *
 * It does not slow the suite down: an assertion that will pass still passes on
 * its first attempt. Only a genuine failure takes longer to be reported.
 */
configure({ asyncUtilTimeout: 5000 });
