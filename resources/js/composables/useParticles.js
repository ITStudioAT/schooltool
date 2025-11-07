import { ref, onMounted, onUnmounted } from 'vue'

export function useParticles(options = {}) {
    const canvas = ref(null)
    const ctx = ref(null)
    const particles = ref([])
    const animationFrame = ref(null)

    // Default Settings
    const settings = {
        count: options.count || 50,
        lineOpacity: options.lineOpacity || 0.15,
        connectionDistance: options.connectionDistance || 120,
        speed: options.speed || 0.3,
        particleColor: options.particleColor || '99, 102, 241', // RGB für rgba()
        lineColor: options.lineColor || '99, 102, 241',
    }

    const resizeCanvas = () => {
        if (!canvas.value) return
        canvas.value.width = window.innerWidth
        canvas.value.height = window.innerHeight
    }

    const createParticles = () => {
        particles.value = []
        if (!canvas.value) return

        for (let i = 0; i < settings.count; i++) {
            particles.value.push({
                x: Math.random() * canvas.value.width,
                y: Math.random() * canvas.value.height,
                vx: (Math.random() - 0.5) * settings.speed,
                vy: (Math.random() - 0.5) * settings.speed,
                radius: 1 + Math.random() * 1.5,
            })
        }
    }

    const updateParticle = (particle) => {
        if (!canvas.value) return

        particle.x += particle.vx
        particle.y += particle.vy

        if (particle.x < 0 || particle.x > canvas.value.width) particle.vx *= -1
        if (particle.y < 0 || particle.y > canvas.value.height) particle.vy *= -1
    }

    const drawParticle = (particle) => {
        if (!ctx.value) return

        ctx.value.beginPath()
        ctx.value.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2)
        ctx.value.fillStyle = `rgba(${settings.particleColor}, ${settings.lineOpacity * 2})`
        ctx.value.fill()
    }

    const drawConnections = () => {
        if (!ctx.value) return

        for (let i = 0; i < particles.value.length; i++) {
            for (let j = i + 1; j < particles.value.length; j++) {
                const dx = particles.value[i].x - particles.value[j].x
                const dy = particles.value[i].y - particles.value[j].y
                const distance = Math.sqrt(dx * dx + dy * dy)

                if (distance < settings.connectionDistance) {
                    const opacity = (1 - distance / settings.connectionDistance) * settings.lineOpacity
                    ctx.value.beginPath()
                    ctx.value.strokeStyle = `rgba(${settings.lineColor}, ${opacity})`
                    ctx.value.lineWidth = 1
                    ctx.value.moveTo(particles.value[i].x, particles.value[i].y)
                    ctx.value.lineTo(particles.value[j].x, particles.value[j].y)
                    ctx.value.stroke()
                }
            }
        }
    }

    const animate = () => {
        if (!canvas.value || !ctx.value) return

        ctx.value.clearRect(0, 0, canvas.value.width, canvas.value.height)

        particles.value.forEach((particle) => {
            updateParticle(particle)
            drawParticle(particle)
        })

        drawConnections()

        animationFrame.value = requestAnimationFrame(animate)
    }

    const init = (canvasRef) => {
        if (!canvasRef) {
            console.error('Canvas ref not provided')
            return
        }

        canvas.value = canvasRef
        ctx.value = canvas.value.getContext('2d')
        resizeCanvas()
        createParticles()
        animate()
    }

    const cleanup = () => {
        if (animationFrame.value) {
            cancelAnimationFrame(animationFrame.value)
        }
        window.removeEventListener('resize', resizeCanvas)
    }

    // Auto cleanup when component unmounts
    onUnmounted(() => {
        cleanup()
    })

    return {
        canvas,
        particles,
        init,
        cleanup,
        resizeCanvas,
    }
}
