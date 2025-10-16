
export function useValidationRulesSetup() {
    return { required, mail, mailOrNull, minLength, maxLength, exactLength, min, max, date, dateOrNull, shortDate, minOrNull, maxOrNull, time, timeOrNull, decimalOrNull, passwordMatch };
}


export function required() {
    return (v) => (!!v) || 'Es muss etwas eingegeben werden.';
}


export function mail() {
    return (v) => (/^(([^<>()[\]\\.,;:\s@']+(\.[^<>()\\[\]\\.,;:\s@']+)*)|('.+'))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(v)) || 'Es muss sich um eine gültige E-Mail handeln.';
}

export function passwordMatch(originalPassword) {
    return (v) => v === originalPassword || "Kennwörter stimmen nicht überein.";
}


export function minLength(minLength) {
    return (v) => (v && v.length >= minLength) || "Die Eingabe ist zu kurz (min. " + minLength + " Zeichen)";
}

export function maxLength(maxLength) {
    return (v) => (v == null || v.length <= maxLength) || "Die Eingabe ist zu lang (max. " + maxLength + " Zeichen)";
}

export function exactLength(length) {
    return (v) => (v && v.length == length) || "Die Eingabe muss exakt " + length + " Zeichen haben";
}

export function min(number) {
    return (v) => (Number(v) >= number) || "Die Eingabe muss mindestens " + number + " betragen";
}

export function max(number) {
    return (v) => (Number(v) <= number) || "Die Eingabe darf maximal " + number + " betragen";
}

export function date() {
    return (v) => {
        var valid;
        if (v) { valid = checkDate(v); } else { valid = false; }

        if (valid) { return true; } else {
            return "Das Datum muss dem Format JJJJ-MM-TT entsprechen.";
        }

    };
}

export function dateOrNull() {
    return (v) => {
        var valid;
        if (v) { valid = checkDate(v); } else { valid = true; }

        if (valid) { return true; } else {
            return "Das Datum muss dem Format JJJJ-MM-TT entsprechen oder leer sein.";
        }

    };
}

export function time() {
    return (v) => {
        var valid;
        if (v) { valid = checkTime(v); } else { valid = false; }

        if (valid) { return true; } else {
            return "Die Uhrzeit muss dem Format HH:MM entsprechen.";
        }

    };
}

export function timeOrNull() {
    return (v) => {
        var valid;
        if (v) { valid = checkTime(v); } else { valid = true; }

        if (valid) { return true; } else {
            return "Die Uhrzeit muss dem Format HH:MM entsprechen oder leer sein.";
        }

    };
}





export function minOrNull(number) {
    return (v) => {
        var valid = (v == null || v >= number);
        return valid;

    }
}

export function maxOrNull(number) {
    return (v) => {
        var valid = (v == null || v <= number);
        return valid;

    }
}


export function decimalOrNull(number) {

    return (v) => {
        const europeanDecimalPattern = /^-?\d+(,\d+)?$/;
        var valid = (v == null || v == "" || europeanDecimalPattern.test(v));
        if (valid) { return true; } else {
            return "Es muss ich um eine gültige Dezimalzahl handeln oder leer sein.";
        }

    }
}


export function mailOrNull() {
    return (v) => (v == null || v == '' || (/^(([^<>()[\]\\.,;:\s@']+(\.[^<>()\\[\]\\.,;:\s@']+)*)|('.+'))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(v))) || 'Es muss sich um eine gültige E-Mail handeln.';
}


export function shortDate(dt) {
    return (v) => {
        var valid;
        if (v) { valid = checkShortDate(v); } else { valid = false; }

        if (valid) { return true; } else {
            return "Das Datum muss dem Format MM-TT entsprechen.";
        }
    };
}


function checkTime(v) {
    var valid = true;
    valid &&= v.length == 5;
    valid &&= v.substr(2, 1) == ":";

    var hour = parseInt(v.substr(0, 2));
    valid &&= hour >= 0;
    valid &&= hour <= 24;

    var minute = parseInt(v.substr(3, 2));
    valid &&= minute >= 0;
    valid &&= minute <= 59;

    return valid;
}

function checkDate(v) {
    var valid = true;
    valid &&= v.length == 10;
    valid &&= v.substr(4, 1) == "-";
    valid &&= v.substr(7, 1) == "-";
    valid &&= parseInt(v.substr(0, 4)) >= 1900;
    valid &&= parseInt(v.substr(0, 4)) <= 2100;

    var month = parseInt(v.substr(5, 2));
    valid &&= month >= 1;
    valid &&= month <= 12;

    var day = parseInt(v.substr(8, 2));
    valid &&= day >= 1;

    if ([1, 3, 5, 7, 8, 10, 12].includes(month)) {
        valid &&= day <= 31;
    }

    if ([4, 6, 9, 11].includes(month)) {
        valid &&= day <= 30;
    }

    if (month == 2) valid &&= day <= 29;

    return valid;
}


function checkShortDate(v) {
    var valid = true;
    valid &&= v.length == 5;
    valid &&= v.substr(2, 1) == "-";
    var month = parseInt(v.substr(0, 2));
    valid &&= month >= 1;
    valid &&= month <= 12;

    var day = parseInt(v.substr(3, 2));
    valid &&= day >= 1;

    if ([1, 3, 5, 7, 8, 10, 12].includes(month)) {
        valid &&= day <= 31;
    }

    if ([4, 6, 9, 11].includes(month)) {
        valid &&= day <= 30;
    }

    if (month == 2) valid &&= day <= 29;

    return valid;
}