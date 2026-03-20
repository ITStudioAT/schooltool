import 'vuetify/styles'
import { createVuetify } from 'vuetify'

const lightTheme = {
    dark: false,
    colors: {
        // Main brand color for buttons, links, highlights
        primary: '#3949AB',         // indigo.darken1
        // Used for secondary buttons, cards, less emphasis
        secondary: '#B0BEC5',       // blue-grey.lighten3
        // Optional highlight or accent tone
        accent: '#18FFFF',          // cyan.accent3
        // App background
        background: '#FAFAFA',      // grey.lighten5
        // Card and surface background
        surface: '#FFFFFF',         // white
        // Default text color
        text: '#424242',            // grey.darken3
        // For success messages or icons
        success: '#00897B',         // teal.darken1
        // For errors, alerts
        error: '#E53935',           // red.darken1
    },
}

const darkTheme = {
    dark: true,
    colors: {

        // Main brand color for buttons, links, highlights
        primary: '#7986CB',         // indigo.lighten2
        // Used for secondary buttons, cards, less emphasis
        secondary: '#455A64',       // blue-grey.darken3
        // Optional highlight or accent tone
        accent: '#00E5FF',          // cyan.accent2
        // App background
        background: '#212121',      // grey.darken4
        // Card and surface background
        surface: '#424242',         // grey.darken3
        // Default text color
        text: '#E0E0E0',            // grey.lighten4
        // For success messages or icons
        success: '#4DB6AC',         // teal.lighten2
        // For errors, alerts
        error: '#EF5350',           // red.lighten2
    },
}

export default createVuetify({
    theme: {
        defaultTheme: 'light',
        variations: {
            colors: ['primary', 'secondary', 'accent', 'background', 'surface', 'text', 'success', 'error'],
            lighten: 4,
            darken: 4,
        },
        themes: {
            light: lightTheme, // Use your custom theme here
            dark: darkTheme, // Use your custom theme here
        },

    },

})
