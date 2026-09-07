import { useState } from 'react';
import Boton from './Boton';
import Panel from './Panel';

/**
 * Pantalla de entrada.
 *
 * Sólo bloquea guardar, no calcular: se puede usar todo el asistente sin cuenta
 * y entrar recién cuando haga falta conservar el trabajo. Pedir registro antes
 * de saber si la herramienta sirve espanta a quien venía a probarla.
 */
export default function Entrada({ onEntro, onCancelar }) {
    const [email, setEmail] = useState('');
    const [clave, setClave] = useState('');
    const [error, setError] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const enviar = async (e) => {
        e.preventDefault();
        setEnviando(true);
        setError(null);

        try {
            await onEntro(email, clave);
        } catch (err) {
            setError(err.message);
        } finally {
            setEnviando(false);
        }
    };

    return (
        <div className="plano" style={{ minHeight: '100dvh', display: 'grid', placeItems: 'center', padding: 24 }}>
            <Panel style={{ width: 'min(28rem, 100%)' }}>
                <form onSubmit={enviar} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                    <h1 className="titulo" style={{ margin: 0, fontSize: '1.4rem' }}>Entrar</h1>

                    <p className="campo__nota" style={{ margin: 0 }}>
                        Sólo hace falta para guardar. Calcular no lo necesita.
                    </p>

                    {error && <p className="aviso" style={{ margin: 0 }}>{error}</p>}

                    <label className="campo">
                        <span className="campo__rotulo">Correo</span>
                        <input
                            className="entrada"
                            type="email"
                            autoComplete="username"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                        />
                    </label>

                    <label className="campo">
                        <span className="campo__rotulo">Contraseña</span>
                        <input
                            className="entrada"
                            type="password"
                            autoComplete="current-password"
                            required
                            value={clave}
                            onChange={(e) => setClave(e.target.value)}
                        />
                    </label>

                    <Boton principal type="submit" disabled={enviando}>
                        {enviando ? 'Entrando…' : 'Entrar'}
                    </Boton>

                    <Boton onClick={onCancelar}>Seguir sin cuenta</Boton>

                    <p className="campo__nota" style={{ margin: 0 }}>
                        ¿No tienes cuenta todavía? Créala desde el backend con{' '}
                        <code>php artisan construprec:usuario</code>.
                    </p>
                </form>
            </Panel>
        </div>
    );
}
