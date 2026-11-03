import { expect, it } from 'vitest'
import { ipv4Network, matchingRoute, packetJourney, routerExampleRoutes } from '@/helpers/networkAddressing'

it('calculates the mask, network and boundaries across octets and validates inputs', () => {
    expect(ipv4Network('192.168.10.20', 24)).toMatchObject({ mask: '255.255.255.0', network: '192.168.10.0', broadcast: '192.168.10.255', firstHost: '192.168.10.1', lastHost: '192.168.10.254', addressCount: 256 })
    expect(ipv4Network('192.168.10.130', 26)).toMatchObject({ mask: '255.255.255.192', network: '192.168.10.128', broadcast: '192.168.10.191', addressCount: 64 })
    expect(ipv4Network('172.31.255.255', 12)?.network).toBe('172.16.0.0')
    expect(ipv4Network('203.0.113.80', 0)?.network).toBe('0.0.0.0')
    expect(ipv4Network('192.168.300.1', 24)).toBeNull()
    expect(ipv4Network('192.168.1.1', 33)).toBeNull()
})

it('selects the longest matching prefix rather than the default or the first matching row', () => {
    expect(matchingRoute('192.168.20.30', [...routerExampleRoutes].reverse())).toMatchObject({ network: '192.168.20.0', prefix: 24 })
    expect(matchingRoute('192.168.30.40', routerExampleRoutes)).toMatchObject({ prefix: 16 })
    expect(matchingRoute('203.0.113.80', routerExampleRoutes)).toMatchObject({ prefix: 0 })
    expect(matchingRoute('203.0.113.80', routerExampleRoutes.slice(0, 2))).toBeNull()
})

it('configures the client before normal routing and resolves DNS using the local network first', () => {
    const steps = packetJourney('internet')
    expect(steps.slice(1, 5).map(step => step.id)).toEqual(['discover', 'offer', 'request', 'ack'])
    expect(steps.find(step => step.id === 'offer')?.configured).toBe(false)
    expect(steps.findIndex(step => step.id === 'ack')).toBeLessThan(steps.findIndex(step => step.id === 'dns-route'))
    expect(steps.findIndex(step => step.id === 'dns-arp')).toBeLessThan(steps.findIndex(step => step.id === 'dns-query'))
    expect(steps.findIndex(step => step.id === 'dns-answer')).toBeLessThan(steps.findIndex(step => step.id === 'network'))
    expect(packetJourney('local', 'static').some(step => step.id === 'discover')).toBe(false)
})

it('delivers locally without gateway routing or NAT and changes MAC addresses between routed subnets', () => {
    const local = packetJourney('local')
    expect(local.some(step => ['router-route', 'nat'].includes(step.id))).toBe(false)
    expect(local.find(step => step.id === 'local-frame')).toMatchObject({ destination: '192.168.10.30', nextMac: 'MAC des Druckers' })
    const routed = packetJourney('subnet')
    expect(routed.find(step => step.id === 'local-frame')).toMatchObject({ source: '192.168.10.20', destination: '192.168.20.30', nextMac: 'MAC des Routers · VLAN 10' })
    expect(routed.find(step => step.id === 'server-frame')).toMatchObject({ source: '192.168.10.20', destination: '192.168.20.30', nextMac: 'MAC des Servers' })
    expect(routed.some(step => step.nat)).toBe(false)
})

it('shows both NAT directions and stops a blocked flow without pretending an answer arrives', () => {
    const steps = packetJourney('internet')
    expect(steps.find(step => step.id === 'nat')).toMatchObject({ source: '198.51.100.7', destination: '203.0.113.80', nat: true })
    expect(steps.find(step => step.id === 'return')).toMatchObject({ source: '203.0.113.80', destination: '198.51.100.7' })
    expect(steps.find(step => step.id === 'reverse-nat')).toMatchObject({ source: '203.0.113.80', destination: '192.168.10.20' })
    expect(steps.at(-1)?.complete).toBe(true)
    const blocked = packetJourney('internet', 'dhcp', true)
    expect(blocked.at(-1)).toMatchObject({ id: 'firewall', blocked: true })
    expect(blocked.some(step => step.nat || step.complete)).toBe(false)
})
