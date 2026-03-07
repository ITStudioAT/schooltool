import childProcess from 'node:child_process'
import { EventEmitter } from 'node:events'
import { syncBuiltinESMExports } from 'node:module'

const originalExec = childProcess.exec.bind(childProcess)

childProcess.exec = (command, ...args) => {
    if (String(command).trim().toLowerCase() === 'net use') {
        const callback = args.find((arg) => typeof arg === 'function')
        const child = new EventEmitter()
        child.pid = 0
        child.stdin = null
        child.stdout = null
        child.stderr = null
        child.kill = () => true

        queueMicrotask(() => {
            if (callback) {
                callback(null, '', '')
            }
            child.emit('exit', 0, null)
            child.emit('close', 0, null)
        })

        return child
    }

    return originalExec(command, ...args)
}

syncBuiltinESMExports()
