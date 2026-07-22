import { useState, useEffect } from 'react';
import { IoSettingsOutline, IoKeyOutline, IoPersonAdd } from 'react-icons/io5';
import { LuFingerprint } from 'react-icons/lu';
import useLazyFetch from '../../hooks/useLazyFetch/useLazyFetch';
import { CoreWindow, CoreGroup, CoreVSep, CoreButtonSquare } from '../../components';
import { showConfirm } from '../../components/CoreConfirm/CoreConfirm';
import { setBackHandler, clearBackHandler, isRunningInWebView, getSessionData } from '../../util/util';
import ModalCambiarClave from './ModalCambiarClave';
import ModalCrearUsuario from './ModalCrearUsuario';

const CONFIG_COLOR = '#1976d2';

const Configuracion = ({ onBack }) => {
    const { fetchData, BackdropLoader, ErrorModal } = useLazyFetch();

    const [showModalClave, setShowModalClave] = useState(false);
    const [showModalUsuario, setShowModalUsuario] = useState(false);

    const sessionData = getSessionData();
    const isAdmin = sessionData?.user?.admin === '1';

    useEffect(() => {
        setBackHandler(() => {
            if (typeof onBack === 'function') {
                onBack();
            }
        });
        return () => clearBackHandler();
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const handleSaveClave = async ({ actual, nueva, confirmacion }, closeModal) => {
        try {
            const response = await fetchData('changePassword', { actual, nueva, confirmacion });
            if (response?.status) {
                setShowModalClave(false);
                if (typeof closeModal === 'function') {
                    closeModal();
                }
            }
        } catch {
            // el error lo muestra useLazyFetch
        }
    };

    const handleSaveUsuario = async ({ nickname, nombre, email, pwd, admin }, closeModal) => {
        try {
            const response = await fetchData('createUsuario', { nickname, nombre, email, pwd, admin });
            if (response?.status) {
                setShowModalUsuario(false);
                if (typeof closeModal === 'function') {
                    closeModal();
                }
            }
        } catch {
            // el error lo muestra useLazyFetch
        }
    };

    const containerStyles = {
        display: 'flex',
        flexDirection: 'column',
        height: '100%',
        backgroundColor: '#f8fafc',
    };

    const headerStyles = {
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        padding: '8px 16px',
        backgroundColor: '#fff',
        borderBottom: '1px solid #e2e8f0',
        boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
        flexShrink: 0,
    };

    const titleStyles = {
        fontSize: '15px',
        fontWeight: '700',
        color: '#1e293b',
        margin: 0,
    };

    return (
        <>
            <div style={containerStyles}>
                <div style={headerStyles}>
                    <IoSettingsOutline size={22} color={CONFIG_COLOR} />
                    <h2 style={titleStyles}>Configuracion</h2>
                </div>

                <div style={{ flex: 1, overflowY: 'auto', padding: '16px' }}>
                    <CoreWindow
                        icon={<IoSettingsOutline size={20} color="#fff" />}
                        title="Preferencias"
                        color={CONFIG_COLOR}
                    >
                        <CoreGroup label="Cuenta">
                            <CoreButtonSquare
                                label="Cambiar contrasena"
                                icon={<IoKeyOutline size={16} />}
                                color={CONFIG_COLOR}
                                onClick={() => setShowModalClave(true)}
                                ignoreFormState={true}
                                style={{ width: '100%' }}
                            />
                            {isAdmin && (
                                <>
                                    <CoreVSep size={8} />
                                    <CoreButtonSquare
                                        label="Crear usuario"
                                        icon={<IoPersonAdd size={16} />}
                                        color={CONFIG_COLOR}
                                        onClick={() => setShowModalUsuario(true)}
                                        ignoreFormState={true}
                                        style={{ width: '100%' }}
                                    />
                                </>
                            )}
                        </CoreGroup>
                        <CoreVSep size={14} />
                        <CoreGroup label="Biométrico">
                            <CoreButtonSquare
                                label="Eliminar datos biométricos"
                                icon={<LuFingerprint size={16} />}
                                color="#d32f2f"
                                onClick={() => {
                                    showConfirm({
                                        text: '¿Eliminar datos biométricos?\nSe desactivará el acceso con huella. Podrías volver a activarlo desde el login.',
                                        okAction: async () => {
                                            try {
                                                const response = await fetchData('disableBiometric', {});
                                                if (response?.status && isRunningInWebView() && typeof Android !== 'undefined' && typeof Android.disableBiometric === 'function') {
                                                    Android.disableBiometric();
                                                }
                                            } catch {
                                                // el error lo muestra useLazyFetch
                                            }
                                        },
                                    });
                                }}
                                ignoreFormState={true}
                                style={{ width: '100%' }}
                            />
                        </CoreGroup>
                    </CoreWindow>
                </div>

                <BackdropLoader />
                <ErrorModal />
            </div>

            <ModalCambiarClave
                open={showModalClave}
                onClose={() => setShowModalClave(false)}
                onSave={handleSaveClave}
            />

            <ModalCrearUsuario
                open={showModalUsuario}
                onClose={() => setShowModalUsuario(false)}
                onSave={handleSaveUsuario}
            />
        </>
    );
};

export default Configuracion;
