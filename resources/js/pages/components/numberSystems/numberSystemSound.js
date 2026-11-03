function soundSequence(context, output) {
    const sources = []
    function tone(frequency, time, duration, volume, type = 'sine', endFrequency = frequency) {
        const oscillator = context.createOscillator()
        const envelope = context.createGain()
        oscillator.type = type
        oscillator.frequency.setValueAtTime(frequency, time)
        oscillator.frequency.exponentialRampToValueAtTime(endFrequency, time + duration)
        envelope.gain.setValueAtTime(.0001, time)
        envelope.gain.exponentialRampToValueAtTime(volume, time + .035)
        envelope.gain.exponentialRampToValueAtTime(.0001, time + duration)
        oscillator.connect(envelope); envelope.connect(output)
        oscillator.onended = () => { oscillator.disconnect(); envelope.disconnect() }
        oscillator.start(time); oscillator.stop(time + duration + .02)
        sources.push(oscillator)
    }
    return { tone, stop: () => sources.forEach(source => { try { source.stop() } catch { /* Already ended. */ } }) }
}

function noteFrequency(note) { return 440 * 2 ** ((note - 69) / 12) }

export function scheduleMachineSound(context, output, reward) {
    const start = context.currentTime + .02
    const finish = reward.chapterProgress === 100
    const { tone, stop } = soundSequence(context, output)
    // A short motor sweep followed by a rising, resolved signal.
    tone(55, start, finish ? 1.25 : .8, .18, 'triangle', finish ? 165 : 110)
    const notes = finish ? [48, 55, 60, 64, 67, 72] : [48, 55, 60, 64]
    notes.forEach((note, index) => tone(440 * 2 ** ((note - 69) / 12), start + .16 + index * .12, .36, .13))
    if (finish) { tone(523.25, start + .88, .7, .075); tone(783.99, start + .88, .7, .055) }
    return stop
}

export function scheduleArchiveSound(context, output, reward) {
    const { tone, stop } = soundSequence(context, output)
    const start = context.currentTime + .02, finish = reward.chapterProgress === 100
    // Small glassy data packets settle into a stable chord.
    const notes = finish ? [72, 76, 79, 84, 88] : [72, 76, 79]
    notes.forEach((note, index) => tone(noteFrequency(note), start + index * .08, .18, .07))
    tone(130.81, start + .12, .4, .05)
    if (finish) { [60, 64, 67].forEach(note => tone(noteFrequency(note), start + .55, .65, .06)) }
    return stop
}

export function scheduleVaultSound(context, output, reward) {
    const { tone, stop } = soundSequence(context, output)
    const start = context.currentTime + .02
    // A mechanical latch, three pins, then the released vault door.
    tone(130, start, .12, .14, 'triangle', 55)
    const pins = [330, 370, 440]
    pins.forEach((frequency, index) => tone(frequency, start + .18 + index * .14, .09, .06, 'triangle', frequency * .65))
    if (reward.chapterProgress === 100) {
        tone(65, start + .56, .52, .16, 'triangle', 98)
        const chord = [48, 55, 60]
        chord.forEach(note => tone(noteFrequency(note), start + .72, .55, .055))
    }
    return stop
}

export function scheduleRadioSound(context, output, reward) {
    const { tone, stop } = soundSequence(context, output)
    const start = context.currentTime + .02, finish = reward.chapterProgress === 100
    // A searching signal locks into a clear connection melody.
    for (let index = 0; index < (finish ? 3 : 2); index++) {
        tone(520 + index * 130, start + index * .16, .14, .07, 'sine', 1040 + index * 130)
    }
    if (finish) {
        const connection = [60, 64, 67, 72]
        connection.forEach((note, index) => tone(noteFrequency(note), start + .5 + index * .12, .32, .08))
    } else { tone(660, start + .34, .32, .07) }
    return stop
}

export function scheduleFinaleSound(context, output) {
    const { tone, stop } = soundSequence(context, output)
    const start = context.currentTime + .02
    tone(180, start, .48, .1, 'triangle', 720)
    const melody = [60, 64, 67, 72, 76, 79, 84]
    melody.forEach((note, index) => tone(noteFrequency(note), start + .65 + index * .15, .3, .11, 'triangle'))
    for (const delay of [.82, 1.2, 1.65, 2.3, 3.05, 4.05]) {
        tone(110, start + delay, .22, .15, 'triangle', 42)
        tone(1174.66, start + delay + .04, .22, .035)
        tone(1567.98, start + delay + .12, .24, .025)
    }
    for (const note of [60, 64, 67, 72]) { tone(noteFrequency(note), start + 1.85, 1.1, .07, 'triangle') }
    return stop
}

export function scheduleNumberSystemSound(context, output, reward) {
    if (reward.kind === 'complete') { return scheduleFinaleSound(context, output) }
    switch (reward.chapter) {
        case 0: return scheduleMachineSound(context, output, reward)
        case 1: return scheduleArchiveSound(context, output, reward)
        case 2: return scheduleVaultSound(context, output, reward)
        case 3: return scheduleRadioSound(context, output, reward)
        default: return null
    }
}

export function createNumberSystemSound() {
    let context = null, output = null, stopCurrent = null, unlocking = null, disposed = false, generation = 0
    const Context = window.AudioContext || window.webkitAudioContext
    async function unlock() {
        if (!Context || disposed) { return false }
        if (unlocking) { return unlocking }
        try {
            if (!context) {
                context = new Context()
                output = context.createGain(); output.gain.value = 2; output.connect(context.destination)
            }
            unlocking = (context.state === 'suspended' ? context.resume() : Promise.resolve())
                .then(() => !disposed && context.state === 'running').catch(() => false).finally(() => { unlocking = null })
            return await unlocking
        } catch { return false }
    }
    function stop() { ++generation; stopCurrent?.(); stopCurrent = null }
    async function play(reward) {
        stop()
        const ticket = generation
        if (unlocking) { await unlocking }
        if (disposed || ticket !== generation || !context || context.state !== 'running') { return false }
        stopCurrent = scheduleNumberSystemSound(context, output, reward)
        return Boolean(stopCurrent)
    }
    function dispose() {
        disposed = true; stop()
        if (context && context.state !== 'closed') { void context.close().catch(() => {}) }
    }
    return { available: Boolean(Context), unlock, play, stop, dispose }
}
