<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if ((int) ($user->id ?? 0) === 1 || (int) ($user->role ?? 0) === 1) {
                return true;
            }

            return null;
        });

        Gate::define('Superuser', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                return false;

            }
        });     

        Gate::define('dashboard-operativo-ver', function ($user) {
            if (in_array((int) ($user->role ?? 0), [4, 6], true)) {
                return true;
            }

            return $this->isDashboardOnlyUser($user);
        });

        Gate::define('dashboard-operativo-gestionar', function ($user) {
            return in_array((int) ($user->role ?? 0), [4, 6], true);
        });

          Gate::define('Superuser-administrador-consultor-auditor', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1) {
                    return true;
                } else {
                    
                    if ($user->role == 4) {
                        return true;
                    } else {
                        
                        if ($user->role == 6) {
                            return true;
                        } else {
                            
                            return false;
            
                        }
        
        
                    }
    
                }

            }
        });   
        
        Gate::define('Superuser-administrador-escrutador-consultor-auditor-candidato', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1 && $user->codzon !== 'Ruta') {
                    return true;
                } else {
                    
                    if ($user->role == 2) {
                        return true;
                    } else {
                        
                        if ($user->role == 4) {
                            return true;
                        } else {
                            
                            if ($user->role == 6) {
                                return true;
                            } else {
                                
                                if ($user->role == 1 && $user->candidato !== '101') {
                                    return true;
                                } else {
                                    return false;
                                }
                                
                
                            }
            
                        }
        
                    }
    
                }

            }
        });  

        Gate::define('Superuser-administrador-consultor', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1) {
                    return true;
                } else {
                    
                    if ($user->role == 4) {
                        return true;
                    } else {
                                        
                            
                            return false;
            
                        
        
        
                    }
    
                }

            }
        });     
        Gate::define('Superuser-administrador-escrutador-consultor-auditor', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1 && $user->codzon !== 'Ruta') {
                    return true;
                } else {
                    
                    if ($user->role == 2) {
                        return true;
                    } else {
                        
                        if ($user->role == 4) {
                            return true;
                        } else {
                            
                            if ($user->role == 6) {
                                return true;
                            } else {
                                
                                return false;
                                
                
                            }
            
                        }
        
                    }
    
                }

            }
        });  
        Gate::define('Superuser-administrador-crisis', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1 && $user->codzon !== 'Ruta') {
                    return true;
                } else {
                    
                    if ($user->role == 7) {
                        return true;
                    } else {
                        
                        return false;
                    }
    
                }

            }
        });  
             
        Gate::define('Superuser-administrador-escrutador-coordinador-consultor', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1) {
                    return true;
                } else {
                    
                    if ($user->role == 2) {
                        return true;
                    } else {
                        
                        if ($user->role == 3) {
                            return true;
                        } else {
                            
                            if ($user->role == 4) {
                                return true;
                            } else {
                                
                                return false;
                
                            }
            
                        }
        
                    }
    
                }

            }
        });     

        Gate::define('Superuser-escrutador-coordinador', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 2) {
                    return true;
                } else {
                    if ($user->role == 3) {
                        return true;
                    } else {
                                                   
                        return false;
                        
                    }
                }

            }
        });     

        Gate::define('Superuser-administrador-escrutador-coordinador-consultor-auditor', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1) {
                    return true;
                } else {
                    
                    if ($user->role == 2) {
                        return true;
                    } else {
                        
                        if ($user->role == 3) {
                            return true;
                        } else {
                            
                            if ($user->role == 4) {
                                return true;
                            } else {
                                
                                if ($user->role == 6) {
                                    return true;
                                } else {
                                    
                                    return false;
                    
                                }
                
                            }
            
                        }
        
                    }
    
                }

            }
        });  
        Gate::define('Superuser-administrador-consultor-validador', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1 && $user->codzon !== 'Ruta') {
                    return true;
                } else {
                    
                    if ($user->role == 4) {
                        return true;
                    } else {
                        
                        if ($user->role == 5) {
                            return true;
                        } else {                                                                              
                                return false;
                                                  
                        }
        
                    }
    
                }

            }
        }); 
        Gate::define('Superuser-administrador-escrutador-auditor', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1 && $user->codzon !== 'Ruta') {
                    return true;
                } else {
                    
                    if ($user->role == 2) {
                        return true;
                    } else {
                        
                        if ($user->role == 6) {
                            return true;
                                } else {
                                    
                                    return false;
                    
                                }
            
                        }
        
                    }
    
                }

            
        });  


        Gate::define('no-editar', function($user){
            if ($user->role == 4) {
                return false;
            } else {
                    return true;
            }
          });

          Gate::define('Superuser-administrador-auditor-escrutador', function($user){
            if ($user->id == 1) {
                return true;
            } else {
                if ($user->role == 1 && $user->codzon !== 'Ruta') {
                    return true;
                } else {
                    
                    if ($user->role == 6) {
                        return true;
                    } else {
                        
                        if ($user->role == 2) {
                            return true;
                        } else {
                            
                            return false;
            
                        }
        
                    }
    
                }

            }
        });  
    }

    private function isDashboardOnlyUser($user): bool
    {
        $roleName = trim((string) optional(Role::find((int) ($user->role ?? 0)))->name);
        $normalized = mb_strtolower($roleName);

        return in_array($normalized, [
            'dashboard operativo',
            'dashboard_operativo',
            'dashboard-operativo',
            'solo dashboard',
            'dashboard pmu',
        ], true);
    }
}
