/**
 * Parse MySQL/PHP datetimes on iOS Safari.
 * new Date("2026-08-21 21:30:00") is Invalid Date on iPhone.
 */
function parseExamDateTime(value) {
    if (value == null || value === '') {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value === 'number') {
        if (!Number.isFinite(value) || value === 0) {
            return null;
        }
        const fromNumber = new Date(value);
        return Number.isNaN(fromNumber.getTime()) ? null : fromNumber;
    }

    const raw = String(value).trim();
    if (!raw || raw === '0') {
        return null;
    }

    if (/[zZ]$/.test(raw) || /[+-]\d{2}:\d{2}$/.test(raw)) {
        const fromIso = new Date(raw);
        return Number.isNaN(fromIso.getTime()) ? null : fromIso;
    }

    const match = raw.match(
        /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?)?/
    );
    if (match) {
        const parsed = new Date(
            Number(match[1]),
            Number(match[2]) - 1,
            Number(match[3]),
            Number(match[4] || 0),
            Number(match[5] || 0),
            Number(match[6] || 0)
        );
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    const fallback = new Date(raw);
    return Number.isNaN(fallback.getTime()) ? null : fallback;
}

function examDateTimeMs(value) {
    const parsed = parseExamDateTime(value);
    return parsed ? parsed.getTime() : 0;
}
