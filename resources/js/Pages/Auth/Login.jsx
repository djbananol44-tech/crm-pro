import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Eye, EyeOff, LogIn, Sparkles, AlertCircle } from 'lucide-react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="Вход в систему" />
            
            {/* Background */}
            <div className="min-h-screen bg-[#0a0a0b] flex items-center justify-center p-4 relative overflow-hidden">
                {/* Gradient Orbs */}
                <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-indigo-500/20 rounded-full blur-[128px] animate-pulse" />
                <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-violet-500/20 rounded-full blur-[128px] animate-pulse" style={{ animationDelay: '1s' }} />
                
                {/* Grid Pattern */}
                <div className="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px]" />
                
                {/* Login Card */}
                <div className="w-full max-w-md relative">
                    {/* Logo */}
                    <div className="text-center mb-10 animate-in" style={{ animationDelay: '100ms' }}>
                        <div className="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-2xl shadow-indigo-500/30 mb-6">
                            <Sparkles className="w-10 h-10 text-white" strokeWidth={1.5} />
                        </div>
                        <h1 className="text-3xl font-bold text-white mb-2">JGGL CRM</h1>
                        <p className="text-zinc-500">AI-powered Business Suite</p>
                    </div>

                    {/* Form Card */}
                    <div className="glass-card-static p-8 animate-in" style={{ animationDelay: '200ms' }}>
                        <h2 className="text-xl font-bold text-white mb-2">Добро пожаловать</h2>
                        <p className="text-sm text-zinc-500 mb-8">Войдите в свой аккаунт для продолжения</p>

                        {/* Errors */}
                        {(errors.email || errors.password) && (
                            <div className="flex items-center gap-3 p-4 mb-6 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm animate-in">
                                <AlertCircle className="w-5 h-5 flex-shrink-0" strokeWidth={1.5} />
                                <span>{errors.email || errors.password}</span>
                            </div>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-5">
                            {/* Email */}
                            <div>
                                <label className="block text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    className="input-premium"
                                    placeholder="email@example.com"
                                    value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                    autoComplete="email"
                                    autoFocus
                                />
                            </div>

                            {/* Password */}
                            <div>
                                <label className="block text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                                    Пароль
                                </label>
                                <div className="relative">
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        className="input-premium pr-12"
                                        placeholder="••••••••"
                                        value={data.password}
                                        onChange={e => setData('password', e.target.value)}
                                        autoComplete="current-password"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 p-2 text-zinc-500 hover:text-white rounded-lg transition-colors"
                                    >
                                        {showPassword ? <EyeOff className="w-4 h-4" strokeWidth={1.5} /> : <Eye className="w-4 h-4" strokeWidth={1.5} />}
                                    </button>
                                </div>
                            </div>

                            {/* Remember */}
                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-3 cursor-pointer group">
                                    <div className="relative">
                                        <input
                                            type="checkbox"
                                            checked={data.remember}
                                            onChange={e => setData('remember', e.target.checked)}
                                            className="sr-only peer"
                                        />
                                        <div className="w-5 h-5 rounded-md bg-surface border border-line/10 peer-checked:bg-indigo-500 peer-checked:border-indigo-500 transition-all" />
                                        <svg className="absolute top-1 left-1 w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity" viewBox="0 0 12 12" fill="none">
                                            <path d="M2 6L5 9L10 3" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                        </svg>
                                    </div>
                                    <span className="text-sm text-zinc-400 group-hover:text-zinc-300 transition-colors">Запомнить меня</span>
                                </label>
                            </div>

                            {/* Submit */}
                            <button
                                type="submit"
                                disabled={processing}
                                className="btn-premium w-full justify-center py-3.5 text-base"
                            >
                                {processing ? (
                                    <svg className="w-5 h-5 animate-spin" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                ) : (
                                    <LogIn className="w-5 h-5" strokeWidth={1.5} />
                                )}
                                {processing ? 'Вход...' : 'Войти'}
                            </button>
                        </form>
                    </div>

                    {/* Demo Credentials */}
                    <div className="mt-8 text-center animate-in" style={{ animationDelay: '300ms' }}>
                        <p className="text-xs text-zinc-600 mb-3">Тестовые аккаунты</p>
                        <div className="inline-flex gap-4 text-xs">
                            <div className="px-3 py-2 rounded-lg bg-surface border border-line/10">
                                <span className="text-zinc-500">Админ:</span>
                                <span className="text-zinc-300 ml-1">admin@crm.test</span>
                            </div>
                            <div className="px-3 py-2 rounded-lg bg-surface border border-line/10">
                                <span className="text-zinc-500">Менеджер:</span>
                                <span className="text-zinc-300 ml-1">manager@crm.test</span>
                            </div>
                        </div>
                        <p className="text-xs text-zinc-600 mt-2">Пароль: <span className="text-zinc-400">admin123</span> / <span className="text-zinc-400">manager123</span></p>
                    </div>
                </div>
            </div>
        </>
    );
}
