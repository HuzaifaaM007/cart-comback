import { createRoot, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
    HashRouter, Routes, Route, NavLink, Navigate, Outlet,
    useParams, useOutletContext,
} from 'react-router-dom';
import './style.css';

const { schema, settings: initialSettings } = window.CCB;
const firstSection = Object.keys(schema)[0];

function MultiSelect({ field, value, onChange }) {
    const selected = Array.isArray(value) ? value : [];
    const remaining = Object.entries(field.options).filter(([k]) => !selected.includes(k));

    return (
        <div className="ccb-multi">
            {selected.length > 0 && (
                <div className="ccb-chips">
                    {selected.map((k) => (
                        <span key={k} className="ccb-chip">
                            {field.options[k] ?? k}
                            <button
                                type="button"
                                aria-label="Remove"
                                onClick={() => onChange(selected.filter((x) => x !== k))}
                            >
                                ×
                            </button>
                        </span>
                    ))}
                </div>
            )}
            <select
                value=""
                onChange={(e) => e.target.value && onChange([...selected, e.target.value])}
            >
                <option value="">{field.placeholder || 'Select...'}</option>
                {remaining.map(([k, label]) => (
                    <option key={k} value={k}>{label}</option>
                ))}
            </select>
        </div>
    );
}


function Field({ field, value, onChange }) {
    let input;
    const [show, setShow] = useState(false);

    switch (field.type) {
        case 'toggle':
            input = (
                <label className="ccb-switch">
                    <input
                        type="checkbox"
                        checked={!!value}
                        onChange={(e) => onChange(e.target.checked ? 1 : 0)}
                    />
                    <span />
                </label>
            );
            break;
        case 'number':
            input = (
                <>
                    <input
                        type='number'
                        style={{ width: 80 }}
                        min={field.min}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                    />
                    {field.suffix && <span>{field.suffix}</span>}
                </>
            );
            break;

        case 'multiselect':
            input = <MultiSelect field={field} value={value} onChange={onChange} />;
            break;
        case 'select':
            input = (
                <select value={value} onChange={(e) => onChange(e.target.value)}>
                    {Object.entries(field.options).map(([k, label]) => (
                        <option key={k} value={k}>{label}</option>
                    ))}
                </select>
            );
            break;
        case 'textarea':
            input = (
                <textarea rows="4" className="large-text" value={value}
                    onChange={(e) => onChange(e.target.value)} />
            );
            break;
        case 'password':
            input = (
                <div className="ccb-password-wrap">
                    <input
                        type={show ? 'text' : 'password'}
                        className="regular-text"
                        placeholder={field.placeholder}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        autoComplete="new-password"
                    />
                    <button type="button" className="ccb-password-toggle" onClick={() => setShow(!show)}>
                        {show ? 'Hide' : 'Show'}
                    </button>
                </div>
            );
            break;
        default: // text, email
            input = (
                <input type={field.type === 'email' ? 'email' : 'text'} className="regular-text"
                    value={value} onChange={(e) => onChange(e.target.value)} />
            );
    }

    return (
        <div className={'ccb-row' + (field.type === 'multiselect' ? ' ccb-row-wide' : '')}>
            <div>
                <strong>{field.label}</strong>
                {field.desc && <p className="ccb-desc">{field.desc}</p>}
            </div>
            <div>{input}</div>
        </div>
    );
}


function SettingsLayout() {
    const [values, setValues] = useState(initialSettings);
    const [status, setStatus] = useState('');

    const save = () => {
        setStatus('Saving...');
        apiFetch({ path: '/cart-comback/v1/settings', method: 'POST', data: values })
            .then((saved) => { setValues(saved); setStatus('Saved.'); })
            .catch(() => setStatus('Error saving.'));
    };

    return (
        <>
            <aside className="ccb-side">
                {Object.entries(schema).map(([key, s]) => (
                    <NavLink key={key} to={key}>{s.label}</NavLink>
                ))}
            </aside>
            <div className="ccb-main">
                <div className="ccb-card">
                    <Outlet context={{ values, setValues }} />
                    <button className="button button-primary" onClick={save}>Save Changes</button>
                    <span className="ccb-msg">{status}</span>
                </div>
            </div>
        </>
    );
}

function SettingsSection() {
    const { section } = useParams();
    const { values, setValues } = useOutletContext();
    const def = schema[section];

    if (!def) return <Navigate to={`/settings/${firstSection}`} replace />;

    return (
        <>
            <h2>{def.label}</h2>
            {Object.entries(def.fields).map(([key, field]) => (
                <Field key={key} field={field} value={values[key]}
                    onChange={(v) => setValues({ ...values, [key]: v })} />
            ))}
        </>
    );
}

function Page({ title }) {
    return (
        <div className="ccb-main">
            <h2>{title}</h2>
            <div className="ccb-card"><p>{title} content.</p></div>
        </div>
    );
}

function Layout() {
    return (
        <>
            <nav className="ccb-topbar">
                {/* <NavLink to="/dashboard">Dashboard</NavLink>
                <NavLink to="/reports">Reports</NavLink> */}
                <NavLink to="/settings">Settings</NavLink>
            </nav>
            <div className="ccb-body"><Outlet /></div>
        </>
    );
}

function App() {
    return (
        <HashRouter>
            <Routes>
                <Route element={<Layout />}>
                    <Route index element={<Navigate to="/settings" replace />} />
                    {/* <Route path="dashboard" element={<Page title="Dashboard" />}  /> */}
                    {/* <Route path="reports" element={<Page title="Reports" />} /> */}
                    <Route path="settings" element={<SettingsLayout />}>
                        <Route index element={<Navigate to={firstSection} replace />} />
                        <Route path=":section" element={<SettingsSection />} />
                    </Route>
                </Route>
            </Routes>
        </HashRouter>
    );
}

const rootEl = document.getElementById('ccb-root');
if (rootEl) createRoot(rootEl).render(<App />);