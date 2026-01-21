# JGGL CRM — Design System

## 🎨 Brand Identity

| Property | Value |
|----------|-------|
| **Primary Color** | Orange `#f97316` |
| **Accent Color** | Amber `#f59e0b` |
| **Background** | Onyx Black `#0e0e0e` |
| **Brand Name** | JGGL CRM |
| **Domain** | jgglgocrm.org |

---

## 🎨 Color Tokens

### Primary (Orange)
| Token | Hex | Usage |
|-------|-----|-------|
| `primary-50` | `#fff7ed` | Lightest background |
| `primary-100` | `#ffedd5` | Light hover |
| `primary-200` | `#fed7aa` | Disabled states |
| `primary-300` | `#fdba74` | Border highlights |
| `primary-400` | `#fb923c` | Hover states |
| `primary-500` | `#f97316` | **Main brand color** |
| `primary-600` | `#ea580c` | Active/pressed |
| `primary-700` | `#c2410c` | Dark variant |

### Accent (Amber)
| Token | Hex | Usage |
|-------|-----|-------|
| `accent-400` | `#fbbf24` | Secondary highlights |
| `accent-500` | `#f59e0b` | Secondary actions |
| `accent-600` | `#d97706` | Active secondary |

### Onyx (Neutrals)
| Token | Hex | Usage |
|-------|-----|-------|
| `onyx-950` | `#080808` | Deepest background |
| `onyx-900` | `#0e0e0e` | **App background** |
| `onyx-850` | `#121212` | Cards, modals |
| `onyx-800` | `#181818` | Input backgrounds |
| `onyx-750` | `#202020` | Dropdowns |
| `onyx-700` | `#2a2a2a` | Hover states |
| `onyx-600` | `#404040` | Borders |
| `onyx-500` | `#606060` | Muted text |
| `onyx-400` | `#808080` | Secondary text |
| `onyx-300` | `#a0a0a0` | Body text |
| `onyx-200` | `#c0c0c0` | Primary text |
| `onyx-100` | `#e0e0e0` | Headings |
| `onyx-50` | `#f5f5f5` | Bright text |

### Semantic Colors
| Token | Color | Hex | Usage |
|-------|-------|-----|-------|
| `success` | Emerald | `#10b981` | Success states |
| `warning` | Amber | `#f59e0b` | Warning states |
| `danger` | Rose | `#f43f5e` | Error states |
| `info` | Sky | `#0ea5e9` | Info states |

---

## 📐 Spacing Scale (4px Base)

| Token | Size | Pixels |
|-------|------|--------|
| `space-1` | `0.25rem` | 4px |
| `space-2` | `0.5rem` | 8px |
| `space-3` | `0.75rem` | 12px |
| `space-4` | `1rem` | 16px |
| `space-6` | `1.5rem` | 24px |
| `space-8` | `2rem` | 32px |

---

## 🔘 Border Radius

| Token | Size | Usage |
|-------|------|-------|
| `radius-sm` | `6px` | Small elements |
| `radius-md` | `8px` | Default |
| `radius-lg` | `12px` | Buttons, inputs |
| `radius-xl` | `16px` | Cards (mobile) |
| `radius-2xl` | `20px` | Cards (desktop) |
| `radius-3xl` | `24px` | Large cards |
| `radius-full` | `9999px` | Pills, avatars |

---

## 📝 Typography

### Font Family
```css
--font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
```

### Font Sizes
| Token | Size | Line Height | Usage |
|-------|------|-------------|-------|
| `text-xs` | 12px | 1rem | Labels, captions |
| `text-sm` | 14px | 1.25rem | Secondary text |
| `text-base` | 16px | 1.5rem | Body (iOS safe) |
| `text-lg` | 18px | 1.75rem | Subheadings |
| `text-xl` | 20px | 1.75rem | Section headers |
| `text-2xl` | 24px | 2rem | Page titles |
| `text-3xl` | 30px | 2.25rem | Large headings |

### Font Weights
| Token | Weight | Usage |
|-------|--------|-------|
| `font-normal` | 400 | Body text |
| `font-medium` | 500 | Labels |
| `font-semibold` | 600 | Headings |
| `font-bold` | 700 | Emphasis |

---

## 👆 Touch Targets (Accessibility)

| Token | Size | Usage |
|-------|------|-------|
| `touch-target` | 44px | **Minimum** (Apple HIG) |
| `touch-target-sm` | 40px | Compact mode |
| `touch-target-lg` | 48px | Large buttons |

---

## 🌟 Shadows

### Soft Shadows (Dark Theme)
| Token | Usage |
|-------|-------|
| `shadow-sm` | Subtle elevation |
| `shadow-md` | Cards |
| `shadow-lg` | Modals |
| `shadow-xl` | Dropdowns |

### Glow Effects
| Token | Color | Usage |
|-------|-------|-------|
| `shadow-glow-primary` | Orange | Primary buttons |
| `shadow-glow-success` | Emerald | Success buttons |
| `shadow-glow-danger` | Rose | Danger buttons |
| `shadow-glow-warning` | Amber | Warning buttons |

---

## 📱 Breakpoints (Mobile-First)

| Token | Width | Device |
|-------|-------|--------|
| `xs` | 375px | iPhone SE, small phones |
| `sm` | 640px | Large phones (landscape) |
| `md` | 768px | Tablets (portrait) |
| `lg` | 1024px | Tablets (landscape), laptops |
| `xl` | 1280px | Desktops |
| `2xl` | 1536px | Large desktops |
| `3xl` | 1920px | Full HD |

---

## 🧩 UI Components

### Button Variants
| Variant | Usage | Appearance |
|---------|-------|------------|
| `primary` | Main CTA | Orange gradient + glow |
| `secondary` | Secondary action | Glass + border |
| `ghost` | Tertiary action | Transparent |
| `danger` | Destructive | Rose gradient + glow |
| `success` | Confirmation | Emerald gradient + glow |

### Button States
- **Hover**: Translate up 1px, increase shadow
- **Active**: Scale 98%, reset translate
- **Focus**: 2px ring in primary color
- **Disabled**: 50% opacity, no cursor

### Badge Variants
| Variant | Usage |
|---------|-------|
| `default` | Neutral info |
| `primary` | Brand highlights |
| `success` | Positive status |
| `warning` | Attention needed |
| `danger` | Critical status |
| `info` | Informational |

### Card
- Glass morphism effect (`backdrop-blur`)
- Border: `1px solid rgba(255, 255, 255, 0.08)`
- Hover: Lighter glass, translate up 0.5px

### Input
- Min-height: 44px (touch target)
- Font-size: 16px (prevents iOS zoom)
- Focus: Orange border + ring

---

## ♿ Accessibility Checklist

- [x] Focus visible on all interactive elements
- [x] Touch targets ≥ 44px
- [x] Text contrast ≥ 4.5:1
- [x] UI contrast ≥ 3:1
- [x] Font-size ≥ 16px for inputs
- [x] Safe area padding for notched devices
- [x] Reduced motion support

---

## 📁 File Locations

| File | Purpose |
|------|---------|
| `resources/css/design-tokens.css` | CSS custom properties |
| `tailwind.config.js` | Tailwind theme config |
| `resources/css/app.css` | Global styles |
| `resources/js/Components/UI/index.jsx` | React UI components |
| `app/Providers/Filament/AdminPanelProvider.php` | Filament theme |

---

## 🔄 Sync Between Admin & Manager

Both interfaces use the same:
- Color palette (Orange + Onyx)
- Typography (Inter)
- Border radius (12px buttons, 20px cards)
- Touch targets (44px minimum)
- Focus states (2px orange ring)

### Filament Overrides
Custom CSS injected via `AdminPanelProvider::renderHook()`:
- Sidebar gradient
- Card glass effect
- Input/button styling
- Focus ring color
