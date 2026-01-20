import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Mail, Lock, Eye, EyeOff, LogIn, Briefcase, AlertTriangle } from 'lucide-react';

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

            <div className="min-h-screen flex items-center justify-center bg-slate-900 p-4">
                {/* Background Pattern */}
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute top-0 left-0 w-full h-full bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/20 via-slate-900 to-slate-900"></div>
                    <div className="absolute -top-40 -right-40 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl"></div>
                    <div className="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>
                    {/* Grid pattern */}
                    <div className="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.02)_1px,transparent_1px)] bg-[size:64px_64px]"></div>
                </div>

                <div className="relative w-full max-w-md">
                    {/* Logo & Header */}
                    <div className="text-center mb-8 animate-fade-in">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl shadow-2xl shadow-blue-500/30 mb-6">
                            <Briefcase className="w-8 h-8 text-white" />
                        </div>
                        <h1 className="text-3xl font-bold text-white tracking-tight">CRM Pro</h1>
                        <p className="text-slate-400 mt-2 text-sm">Войдите в систему для продолжения</p>
                    </div>

                    {/* Form Card */}
                    <div className="bg-slate-800/50 backdrop-blur-xl rounded-3xl p-8 border border-slate-700/50 shadow-2xl animate-fade-in" style={{ animationDelay: '100ms' }}>
                        <form onSubmit={handleSubmit} className="space-y-5">
                            {/* Email */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                                    Email
                                </label>
                                <div className="relative">
                                    <Mail className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500" />
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className={`
                                            w-full pl-12 pr-4 py-3.5 bg-slate-900/50 border rounded-xl text-white placeholder:text-slate-500
                                            focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all
                                            ${errors.email ? 'border-rose-500/50' : 'border-slate-700'}
                                        `}
                                        placeholder="you@example.com"
                                        autoComplete="email"
                                        autoFocus
                                    />
                                </div>
                                {errors.email && (
                                    <p className="mt-2 text-sm text-rose-400 flex items-center gap-1">
                                        <AlertTriangle className="w-3.5 h-3.5" />
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            {/* Password */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                                    Пароль
                                </label>
                                <div className="relative">
                                    <Lock className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500" />
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className={`
                                            w-full pl-12 pr-12 py-3.5 bg-slate-900/50 border rounded-xl text-white placeholder:text-slate-500
                                            focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all
                                            ${errors.password ? 'border-rose-500/50' : 'border-slate-700'}
                                        `}
                                        placeholder="••••••••"
                                        autoComplete="current-password"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
                                    >
                                        {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                    </button>
                                </div>
                                {errors.password && (
                                    <p className="mt-2 text-sm text-rose-400 flex items-center gap-1">
                                        <AlertTriangle className="w-3.5 h-3.5" />
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            {/* Remember */}
                            <div className="flex items-center justify-between">
                                <label className="flex items-center cursor-pointer group">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="w-4 h-4 rounded border-slate-600 bg-slate-900 text-blue-500 focus:ring-blue-500/50 focus:ring-offset-0"
                                    />
                                    <span className="ml-2 text-sm text-slate-400 group-hover:text-slate-300 transition-colors">
                                        Запомнить меня
                                    </span>
                                </label>
                            </div>

                            {/* Submit */}
                            <button
                                type="submit"
                                disabled={processing}
                                className="
                                    w-full flex items-center justify-center gap-2 py-3.5 px-4
                                    bg-gradient-to-r from-blue-500 to-indigo-600 
                                    hover:from-blue-600 hover:to-indigo-700
                                    text-white font-semibold rounded-xl
                                    shadow-lg shadow-blue-500/25 hover:shadow-xl hover:shadow-blue-500/30
                                    focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 focus:ring-offset-slate-800
                                    transition-all duration-200 disabled:opacity-50
                                "
                            >
                                {processing ? (
                                    <>
                                        <svg className="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        <span>Вход...</span>
                                    </>
                                ) : (
                                    <>
                                        <LogIn className="w-5 h-5" />
                                        <span>Войти в систему</span>
                                    </>
                                )}
                            </button>
                        </form>
                    </div>

                    {/* Test Credentials */}
                    <div className="mt-6 bg-amber-500/10 backdrop-blur border border-amber-500/20 rounded-2xl p-4 animate-fade-in" style={{ animationDelay: '200ms' }}>
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" />
                            <div className="text-sm">
                                <p className="font-semibold text-amber-300">Тестовые аккаунты:</p>
                                <div className="mt-2 space-y-1 text-amber-200/80">
                                    <p><span className="font-medium text-amber-200">Админ:</span> admin@crm.test / admin123</p>
                                    <p><span className="font-medium text-amber-200">Менеджер:</span> manager@crm.test / manager123</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Footer */}
                    <p className="mt-8 text-center text-xs text-slate-500">
                        © {new Date().getFullYear()} CRM Pro. Все права защищены.
                    </p>
                </div>
            </div>

            <style>{`
                @keyframes fade-in {
                    from { opacity: 0; transform: translateY(-10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .animate-fade-in {
                    animation: fade-in 0.5s ease-out forwards;
                }
            `}</style>
        </>
    );
}
